#!/usr/bin/env python3
"""Parcours de bout en bout contre la pile lancée par scripts/dev-up.sh (API, IMAP et SMTP de test réels).
Sans dépendance. Code de sortie ≠ 0 au premier écart. Idempotent : chaque exécution utilise un objet unique.

  python3 scripts/e2e.py
"""
import json
import sys
import time
import urllib.error
import urllib.request

API = "http://127.0.0.1:8000"
RUN = str(int(time.time()))
failures = []


def call(method, path, token=None, body=None, merge=False):
    req = urllib.request.Request(API + path, method=method, data=None if body is None else json.dumps(body).encode())
    req.add_header("Accept", "application/json")
    if body is not None:
        req.add_header("Content-Type", "application/merge-patch+json" if merge else "application/json")
    if token:
        req.add_header("Authorization", "Bearer " + token)
    try:
        with urllib.request.urlopen(req, timeout=60) as r:
            raw = r.read()
            return r.status, json.loads(raw) if raw else None
    except urllib.error.HTTPError as e:
        raw = e.read()
        return e.code, json.loads(raw) if raw else None
    except urllib.error.URLError as e:
        sys.exit(f"API injoignable sur {API} ({e.reason}). Lance d'abord scripts/dev-up.sh.")


def check(ok, label):
    print(("  OK   " if ok else "  FAIL ") + label)
    if not ok:
        failures.append(label)


def login(user):
    st, r = call("POST", "/api/auth/dev-login", body={"username": user})
    assert st == 200, f"connexion de {user} impossible ({st})"
    return r["token"]


def sync(token):
    call("POST", "/api/sync", token, {})


def main():
    print("Connexion")
    david, claire, marc = login("david"), login("claire"), login("marc")
    check(call("GET", "/api/suivis")[0] == 401, "l'API refuse un appel sans jeton")
    check(call("GET", "/api/me", david)[1]["username"] == "david", "le jeton de David l'identifie")

    print("Envoi avec suivi, réponse détectée (F-50, F-52, F-65, F-73)")
    subject = f"Test E2E {RUN}"
    acc = call("GET", "/api/mail-accounts", david)[1][0]["id"]
    check("password" not in json.dumps(call("GET", "/api/mail-accounts", david)[1]), "le mot de passe n'est jamais renvoyé")
    st, sent = call("POST", "/api/mails/send", david, {"accountId": acc, "to": ["claire@iut-troyes.fr"], "subject": subject, "body": "Peux-tu confirmer ?", "followHours": 48})
    check(st == 201 and sent["suivi"] is not None, "le message part et le suivi est créé à l'envoi")
    sync(david)
    sync(claire)
    mine = next((s for s in call("GET", "/api/suivis", david)[1] if s["subject"] == subject), None)
    check(mine is not None and mine["direction"] == "waiting" and mine["mailId"] == sent["messageId"], "le suivi pointe sur le Message-ID envoyé")
    check([j["action"] for j in (mine or {"journal": []})["journal"]] == ["created", "sent"], "le journal du suivi est tenu")
    received = next((m for m in call("GET", "/api/mails", claire)[1] if m["subject"] == subject and m["folder"] == "INBOX"), None)
    check(received is not None, "Claire a reçu le message dans sa boîte")
    if received:
        cacc = call("GET", "/api/mail-accounts", claire)[1][0]["id"]
        call("POST", "/api/mails/send", claire, {"accountId": cacc, "to": ["david@iut-troyes.fr"], "subject": "Re: " + subject, "body": "Oui.", "inReplyToMailId": received["id"]})
        sync(david)
        after = next(s for s in call("GET", "/api/suivis", david)[1] if s["subject"] == subject)
        check(after["replyReceived"] is True and after["status"] == "waiting", "la réponse est signalée, sans clôture automatique")
        check(any(n["kind"] == "replyReceived" and n["params"].get("subject") == subject for n in call("GET", "/api/notifications", david)[1]), "une alerte « réponse reçue » existe")
        body = call("GET", f"/api/mails/{received['id']}/body", claire)[1]
        check(body is not None and "confirmer" in " ".join(body["paragraphs"]), "le corps du mail se lit à la demande, en texte brut")
        check(call("GET", f"/api/mails/{received['id']}/body", david)[0] == 404, "un autre utilisateur ne peut pas lire ce mail")

    print("Boîte partagée contact@ (F-44, F-67, F-68)")
    team = [s for s in call("GET", "/api/suivis", david)[1] if s["mailbox"] == "contact" and s["subject"] == "Question d'inscription"]
    check(len(team) == 1, "un même message reçu par quatre membres donne une seule demande")
    if team:
        sid = team[0]["id"]
        call("POST", f"/api/suivis/{sid}/claim", david, {"action": "release"})
        check(call("POST", f"/api/suivis/{sid}/claim", david, {"action": "take"})[1]["takenByName"] == "David", "David prend la demande")
        st, r = call("POST", f"/api/suivis/{sid}/claim", claire, {"action": "take"})
        check(st == 409 and "David" in r["title"], "Claire est prévenue que David s'en occupe")
        check(call("POST", f"/api/suivis/{sid}/claim", claire, {"action": "takeOver"})[1]["takenByName"] == "Claire", "Claire peut reprendre la demande")
        check(call("POST", f"/api/suivis/{sid}/notes", marc, {"text": f"note {RUN}"})[0] == 201, "un membre ajoute une note interne")
        outsider = login("etranger" + RUN[-4:])
        check(call("GET", f"/api/suivis/{sid}", outsider)[0] == 404, "un non-membre ne voit pas la demande")
        call("POST", f"/api/suivis/{sid}/claim", claire, {"action": "release"})

    print("Tâches, réglages, état")
    st, task = call("POST", "/api/tasks", david, {"title": f"Tâche {RUN}", "dueAt": "2026-12-01", "priority": 2})
    check(st == 201, "création d'une tâche")
    check(call("PATCH", f"/api/tasks/{task['id']}", david, {"done": True}, merge=True)[1]["done"] is True, "tâche traitée")
    check(call("GET", "/api/tasks", claire)[1] == [] or all(t["title"] != f"Tâche {RUN}" for t in call("GET", "/api/tasks", claire)[1]), "les tâches sont personnelles")
    check(call("PUT", "/api/settings", david, {"aiMode": "skynet"})[0] == 422, "un réglage invalide est refusé")
    conns = call("GET", "/api/connections", david)[1]
    check(any(c["id"] == "mail" and c["state"] == "ok" for c in conns), "la barre d'état voit la messagerie connectée")

    print()
    print("Tout est bon." if not failures else f"{len(failures)} vérification(s) en échec :\n  - " + "\n  - ".join(failures))
    sys.exit(1 if failures else 0)


if __name__ == "__main__":
    main()
