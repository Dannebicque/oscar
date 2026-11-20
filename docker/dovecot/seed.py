#!/usr/bin/env python3
"""Charge les mails de test dans Dovecot par IMAP APPEND. Sans dépendance (bibliothèque standard).

  python3 docker/dovecot/seed.py            # idempotent : ne recharge pas une boîte déjà remplie
  python3 docker/dovecot/seed.py --reset    # vide les boîtes de test puis recharge
"""
import imaplib
import os
import sys
import time

sys.path.insert(0, os.path.dirname(__file__))
import fixtures  # noqa: E402

HOST = os.environ.get("IMAP_HOST", "127.0.0.1")
PORT = int(os.environ.get("IMAP_PORT", "1143"))
PASSWORD = os.environ.get("IMAP_PASSWORD", "oscar-test")


def connect(user):
    for _ in range(30):  # attend que le conteneur soit prêt
        try:
            c = imaplib.IMAP4(HOST, PORT)
            c.login(user, PASSWORD)
            return c
        except (ConnectionRefusedError, imaplib.IMAP4.error, OSError):
            time.sleep(1)
    sys.exit(f"Impossible de se connecter à {HOST}:{PORT} en tant que {user}. Le conteneur est-il lancé ?")


def count(c, folder):
    if c.select(folder, readonly=True)[0] != "OK":
        return 0
    return len(c.search(None, "ALL")[1][0].split())


def wipe(c, folder):
    if count(c, folder):
        c.select(folder)
        c.store("1:*", "+FLAGS", "\\Deleted")
        c.expunge()


def main():
    reset = "--reset" in sys.argv
    for user in sorted({u for u, _, _ in fixtures.PLAN}):
        c = connect(user)
        if reset:
            for folder in ("INBOX", "Sent"):
                wipe(c, folder)
        elif count(c, "INBOX") or count(c, "Sent"):
            print(f"{user}: déjà rempli, rien à faire (--reset pour recharger)")
            c.logout()
            continue
        loaded = 0
        for u, folder, msg in fixtures.PLAN:
            if u == user:
                c.create(folder)
                c.append(folder, "", imaplib.Time2Internaldate(time.time()), msg.as_bytes().replace(b"{user}", user.encode()))
                loaded += 1
        print(f"{user}: {loaded} messages chargés")
        c.logout()


if __name__ == "__main__":
    main()
