#!/usr/bin/env python3
"""Parcours de bout en bout contre la pile lancée par scripts/dev-up.sh (API, IMAP et SMTP de test réels).
Sans dépendance. Code de sortie ≠ 0 au premier écart. Idempotent : chaque exécution utilise un objet unique.

  python3 scripts/e2e.py
"""
import imaplib
import json
import os
import sys
import time
import urllib.error
import urllib.request

API = os.environ.get("OSCAR_API", "http://127.0.0.1:8000")
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


def purge():
    """Retire des boîtes de test les messages laissés par ce script (objet « Test E2E »), pour garder une démo propre."""
    for user in ("david", "claire", "marc"):
        try:
            c = imaplib.IMAP4("127.0.0.1", 1143)
            c.login(user, "oscar-test")
        except Exception:
            continue
        for line in c.list()[1]:
            folder = line.decode().rsplit(' "', 1)[-1].strip('" ').split(" ")[-1].strip('"')
            if c.select(f'"{folder}"')[0] != "OK":
                continue
            ids = c.search(None, "SUBJECT", '"Test E2E"')[1][0].split()
            for i in ids:
                c.store(i, "+FLAGS", "\\Deleted")
            c.expunge()
        c.logout()


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

    print("Comptes locaux : inscription, connexion, mot de passe oublié, assistant de messagerie")
    email, pwd = f"e2e{RUN}@example.org", "phrase-de-passe-e2e-1"
    st, reg = call("POST", "/api/auth/register", body={"name": "E2E", "email": email, "password": pwd})
    check(st == 201, "inscription d'un compte local")
    check(call("POST", "/api/auth/login", body={"email": email, "password": pwd})[0] == 200, "connexion par adresse et mot de passe")
    check(call("POST", "/api/auth/login", body={"email": email, "password": "faux-faux-faux"})[0] == 401, "un mauvais mot de passe est refusé")
    st, f = call("POST", "/api/auth/forgot", body={"email": email})
    new = "nouvelle-phrase-e2e-2"
    if st == 200 and f and f.get("devLink"):
        tok = f["devLink"].split("token=")[1]
        check(call("POST", "/api/auth/reset", body={"token": tok, "password": new})[0] == 204, "réinitialisation par le lien reçu")
        check(call("POST", "/api/auth/login", body={"email": email, "password": new})[0] == 200, "connexion avec le nouveau mot de passe")
    pw_now = new if f else pwd
    tok_now = call("POST", "/api/auth/login", body={"email": email, "password": pw_now})[1]["token"]
    st, probe = call("POST", "/api/mail-accounts/probe", tok_now, {"imapHost": "127.0.0.1", "imapPort": 1143, "imapEncryption": "none", "smtpHost": "127.0.0.1", "smtpPort": 1025, "smtpEncryption": "none", "username": "david", "password": "oscar-test"})
    check(st == 200 and probe["imap"]["ok"] and probe["smtp"]["ok"], "le test des serveurs réussit avant d'enregistrer le compte")
    check(call("POST", "/api/mail-accounts/probe", tok_now, {"imapHost": "127.0.0.1", "imapPort": 1143, "imapEncryption": "none", "smtpHost": "127.0.0.1", "smtpPort": 1025, "smtpEncryption": "none", "username": "david", "password": "faux"})[1]["imap"]["ok"] is False, "un mauvais mot de passe de messagerie est signalé")
    check(call("DELETE", "/api/me", tok_now, {"password": pw_now})[0] == 204, "suppression du compte (nettoyage)")

    purge()
    print()
    print("Tout est bon." if not failures else f"{len(failures)} vérification(s) en échec :\n  - " + "\n  - ".join(failures))
    sys.exit(1 if failures else 0)


if __name__ == "__main__":
    if "--purge" in sys.argv:
        purge()
        sys.exit(0)
    main()
