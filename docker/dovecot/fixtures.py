"""Mails de test. Scénarios du cahier des charges : réponse (F-52), réponse automatique ignorée, alias (F-44)."""
from email.message import EmailMessage
from email.utils import format_datetime, make_msgid
from datetime import datetime, timedelta, timezone

DOMAIN = "iut-troyes.fr"
NOW = datetime.now(timezone.utc).replace(microsecond=0)


def mail(*, frm, to, subject, body, hours_ago, msgid=None, in_reply_to=None, references=None, extra=None):
    m = EmailMessage()
    m["From"], m["To"], m["Subject"] = frm, to, subject
    m["Date"] = format_datetime(NOW - timedelta(hours=hours_ago))
    m["Message-ID"] = msgid or make_msgid(domain=DOMAIN)
    if in_reply_to:
        m["In-Reply-To"] = in_reply_to
        m["References"] = references or in_reply_to
    for k, v in (extra or {}).items():
        m[k] = v
    m.set_content(body)
    return m


# Un mail envoyé par David, puis la réponse de son destinataire (même fil : In-Reply-To / References).
SENT_ID = "<devis-affiches-1@iut-troyes.fr>"
SENT = mail(frm=f"David <david@{DOMAIN}>", to="Marc Dubois <marc.dubois@imprimerie.fr>", subject="Devis des affiches",
            body="Bonjour Marc,\n\nPourriez-vous m'envoyer le devis pour 40 affiches A2 ?\n\nDavid", hours_ago=52, msgid=SENT_ID)
REPLY = mail(frm="Marc Dubois <marc.dubois@imprimerie.fr>", to=f"David <david@{DOMAIN}>", subject="Re: Devis des affiches",
             body="Bonjour David,\n\nVoici le devis en pièce jointe. Il reste une question sur le format.\n\nMarc",
             hours_ago=2, in_reply_to=SENT_ID)
# Une réponse automatique d'absence : elle ne doit PAS compter comme une réponse (F-52).
AUTO_SENT_ID = "<convention-1@iut-troyes.fr>"
AUTO_SENT = mail(frm=f"David <david@{DOMAIN}>", to="Claire Petit <claire.petit@entreprise.fr>", subject="Convention de stage",
                 body="Bonjour Claire, ci-joint la convention à signer.", hours_ago=20, msgid=AUTO_SENT_ID)
AUTO_REPLY = mail(frm="Claire Petit <claire.petit@entreprise.fr>", to=f"David <david@{DOMAIN}>", subject="Absence : Convention de stage",
                  body="Je suis absente jusqu'au lundi 30.", hours_ago=19, in_reply_to=AUTO_SENT_ID,
                  extra={"Auto-Submitted": "auto-replied", "X-Auto-Response-Suppress": "All"})
# Un mail sans réponse attendue, avec une demande de tâche et de date (extraction F-11).
TASK_MAIL = mail(frm="Sophie Laurent <sophie.laurent@univ-reims.fr>", to=f"David <david@{DOMAIN}>", subject="Planning des soutenances",
                 body="Bonjour David,\n\nPeux-tu confirmer le nombre de participants avant mardi ? Préparation du jury jeudi à 10h.\n\nSophie", hours_ago=30)
# Démonstration de l'IA : une demande de disponibilités (créneaux libres, brouillon de réponse, F-74) et un message long à résumer (F-64).
AVAIL_MAIL = mail(frm="Claire Petit <claire.petit@entreprise.fr>", to=f"David <david@{DOMAIN}>", subject="Disponibilités pour la soutenance",
                  body="Bonjour David,\n\nJ'espère que ta semaine se passe bien. Nous préparons la soutenance de M. Lemoine avec le jury. "
                       "Peux-tu m'indiquer tes disponibilités la semaine du 30 novembre ? Il me faudrait deux créneaux d'une heure.\n\nMerci d'avance,\nClaire", hours_ago=3)
LONG_MAIL = mail(frm="Direction des stages <stages@univ-reims.fr>", to=f"David <david@{DOMAIN}>", subject="Conventions de stage de janvier",
                 body="Bonjour,\n\nLa campagne de conventions de stage de janvier démarre. Chaque responsable de formation doit valider les dossiers de ses étudiants. "
                      "Peux-tu transmettre la liste des étudiants concernés avant vendredi ? Les conventions incomplètes seront renvoyées aux étudiants. "
                      "Une réunion de point d'étape aura lieu jeudi à 14h en salle B12.\n\nCordialement,\nLa Direction des stages", hours_ago=8)
# Alias contact@ : le MÊME message (même Message-ID) arrive chez les quatre membres. Une seule demande (F-44).
ALIAS_ID = "<inscription-camille-1@gmail.com>"
ALIAS = mail(frm="Camille Roux <camille.roux@gmail.com>", to=f"contact@{DOMAIN}", subject="Question d'inscription",
             body="Bonjour, les inscriptions sont-elles encore ouvertes pour la rentrée de janvier ?", hours_ago=5, msgid=ALIAS_ID,
             extra={"Delivered-To": "{user}@" + DOMAIN})
ALIAS_USERS = ["david", "claire", "marc", "lea"]

# (utilisateur, dossier, message)
PLAN = [
    ("david", "Sent", SENT), ("david", "INBOX", REPLY), ("david", "Sent", AUTO_SENT), ("david", "INBOX", AUTO_REPLY), ("david", "INBOX", TASK_MAIL),
    ("david", "INBOX", AVAIL_MAIL), ("david", "INBOX", LONG_MAIL),
] + [(u, "INBOX", ALIAS) for u in ALIAS_USERS]
