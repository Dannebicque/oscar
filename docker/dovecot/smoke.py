#!/usr/bin/env python3
"""Vérifie que le serveur de test est prêt et que les scénarios du cahier des charges sont présents.
Code de sortie ≠ 0 en cas d'écart : utilisable en CI. Sans dépendance.
"""
import email
import imaplib
import os
import sys

HOST = os.environ.get("IMAP_HOST", "127.0.0.1")
PORT = int(os.environ.get("IMAP_PORT", "1143"))
PASSWORD = os.environ.get("IMAP_PASSWORD", "oscar-test")
failures = []


def check(ok, label):
    print(("OK   " if ok else "FAIL ") + label)
    if not ok:
        failures.append(label)


def fetch_all(user, folder):
    c = imaplib.IMAP4(HOST, PORT)
    c.login(user, PASSWORD)
    c.select(folder, readonly=True)
    ids = c.search(None, "ALL")[1][0].split()
    msgs = [email.message_from_bytes(c.fetch(i, "(RFC822)")[1][0][1]) for i in ids]
    c.logout()
    return msgs


def main():
    try:
        inbox = fetch_all("david", "INBOX")
        sent = fetch_all("david", "Sent")
    except (ConnectionRefusedError, imaplib.IMAP4.error, OSError) as e:
        sys.exit(f"FAIL connexion IMAP {HOST}:{PORT} : {e}")
    check(len(inbox) >= 4 and len(sent) >= 2, "david : boîte de réception et envoyés remplis")

    # F-52 : la réponse est reconnue par les en-têtes du fil, pas par l'objet.
    sent_ids = {m["Message-ID"] for m in sent}
    replies = [m for m in inbox if m["In-Reply-To"] in sent_ids]
    check(len(replies) == 2, "deux messages répondent à un envoyé (In-Reply-To)")
    real = [m for m in replies if not m.get("Auto-Submitted")]
    auto = [m for m in replies if m.get("Auto-Submitted")]
    check(len(real) == 1 and len(auto) == 1, "une vraie réponse et une réponse automatique d'absence (à ne pas compter)")

    # F-44 : le même message arrive chez les quatre membres de l'alias, avec le même Message-ID.
    ids = {u: [m["Message-ID"] for m in fetch_all(u, "INBOX") if "contact@" in (m["To"] or "")] for u in ("david", "claire", "marc", "lea")}
    check(all(len(v) == 1 for v in ids.values()), "chaque membre a reçu le mail adressé à contact@")
    check(len({v[0] for v in ids.values() if v}) == 1, "les quatre exemplaires ont le même Message-ID (une seule demande)")

    print("\nTout est prêt." if not failures else f"\n{len(failures)} vérification(s) en échec.")
    sys.exit(1 if failures else 0)


if __name__ == "__main__":
    main()
