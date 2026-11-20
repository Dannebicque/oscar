#!/usr/bin/env python3
"""SMTP de test local : reçoit ce qu'Oscar envoie et le dépose dans les boîtes Dovecot de test (IMAP APPEND).
Un envoi à claire@… arrive dans la boîte de claire ; un envoi à contact@… arrive chez les quatre membres, avec le
même Message-ID (cas de l'alias, F-44). Rien ne sort de la machine. Sans dépendance (asyncio, standard).

  python3 docker/dovecot/smtp-sink.py        # écoute sur 127.0.0.1:1025
"""
import asyncio
import email
import imaplib
import os
import re
import sys
import time

HOST = os.environ.get("SINK_HOST", "127.0.0.1")
PORT = int(os.environ.get("SINK_PORT", "1025"))
IMAP_HOST = os.environ.get("IMAP_HOST", "127.0.0.1")
IMAP_PORT = int(os.environ.get("IMAP_PORT", "1143"))
PASSWORD = os.environ.get("IMAP_PASSWORD", "oscar-test")
USERS = {"david", "claire", "marc", "lea"}
ALIASES = {"contact": sorted(USERS)}
MAX = 10 * 1024 * 1024


def recipients_to_users(rcpts):
    users = []
    for r in rcpts:
        local = r.split("@")[0].lower()
        for u in ALIASES.get(local, [local] if local in USERS else []):
            if u not in users:
                users.append(u)
    return users


def deliver(user, raw):
    try:
        c = imaplib.IMAP4(IMAP_HOST, IMAP_PORT)
        c.login(user, PASSWORD)
        c.create("INBOX")
        c.append("INBOX", "", imaplib.Time2Internaldate(time.time()), raw)
        c.logout()
        return True
    except Exception as e:  # noqa: BLE001
        print(f"  ! livraison à {user} impossible : {e}", file=sys.stderr)
        return False


async def handle(reader, writer):
    async def say(line):
        writer.write((line + "\r\n").encode())
        await writer.drain()

    await say("220 oscar-test-sink ESMTP")
    rcpts, size = [], 0
    while True:
        line = (await reader.readline()).decode(errors="replace").strip()
        if not line:
            break
        cmd = line.upper()
        if cmd.startswith(("EHLO", "HELO")):
            await say("250-oscar-test-sink")
            await say(f"250 SIZE {MAX}")
        elif cmd.startswith("MAIL FROM"):
            rcpts = []
            await say("250 OK")
        elif cmd.startswith("RCPT TO"):
            m = re.search(r"<([^>]+)>", line)
            if m:
                rcpts.append(m.group(1))
            await say("250 OK")
        elif cmd == "DATA":
            await say("354 Fin avec <CRLF>.<CRLF>")
            data = bytearray()
            while True:
                chunk = await reader.readline()
                if chunk in (b".\r\n", b""):
                    break
                data += chunk[1:] if chunk.startswith(b"..") else chunk
                size += len(chunk)
                if size > MAX:
                    break
            msg = email.message_from_bytes(bytes(data))
            targets = recipients_to_users(rcpts)
            ok = [deliver(u, bytes(data)) for u in targets]
            print(f"✉  {msg['Message-ID']} « {msg['Subject']} » → {', '.join(rcpts)} (boîtes : {', '.join(targets) or 'aucune'})")
            await say("250 OK" if all(ok) else "451 Livraison locale impossible")
            rcpts, size = [], 0
        elif cmd in ("RSET", "NOOP"):
            await say("250 OK")
        elif cmd == "QUIT":
            await say("221 Au revoir")
            break
        else:
            await say("502 Commande non gérée")
    writer.close()


async def main():
    server = await asyncio.start_server(handle, HOST, PORT)
    print(f"SMTP de test sur {HOST}:{PORT} → boîtes Dovecot {IMAP_HOST}:{IMAP_PORT}")
    async with server:
        await server.serve_forever()


if __name__ == "__main__":
    asyncio.run(main())
