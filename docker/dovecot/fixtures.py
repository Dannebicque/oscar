"""Mails de test. Scénarios du cahier des charges : réponse (F-52), réponse automatique ignorée, alias (F-44)."""
import base64
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
# Invitation d'agenda (F-25) : un message avec une pièce text/calendar (METHOD:REQUEST), dans trois jours à 14 h (Paris).
def _invitation():
    start = (NOW + timedelta(days=3)).replace(hour=13, minute=0, second=0)  # 13:00 UTC = 14:00 à Paris (hiver)
    fmt = lambda d: d.strftime("%Y%m%dT%H%M%SZ")
    ics = ("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Oscar//demo//FR\r\nMETHOD:REQUEST\r\nBEGIN:VEVENT\r\n"
           "UID:soutenance-lemoine@iut-troyes.fr\r\nSEQUENCE:0\r\nDTSTAMP:" + fmt(NOW) + "\r\nDTSTART:" + fmt(start) + "\r\nDTEND:" + fmt(start + timedelta(hours=1)) +
           "\r\nSUMMARY:Soutenance de stage : M. Lemoine\r\nLOCATION:Salle B12\r\nORGANIZER;CN=Claire Petit:mailto:claire.petit@entreprise.fr\r\n"
           "ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:mailto:david@" + DOMAIN + "\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n")
    m = mail(frm="Claire Petit <claire.petit@entreprise.fr>", to=f"David <david@{DOMAIN}>", subject="Invitation : Soutenance de stage de M. Lemoine",
             body="Bonjour David,\n\nJe t'invite à la soutenance de stage de M. Lemoine, mardi à 14 h en salle B12.\n\nClaire", hours_ago=2)
    m.add_attachment(ics.encode(), maintype="text", subtype="calendar", filename="invitation.ics", params={"method": "REQUEST", "charset": "UTF-8"})
    return m


INVITATION = _invitation()
# Lettre mise en forme (F-16) : tableau, image jointe au corps (cid:), image distante et pixel espion, script que rien ne doit exécuter.
def _newsletter():
    pixel = base64.b64decode("iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAIAAACQkWg2AAAAFklEQVR4nGNk+M9ACmAiSfWohhGlAQBBPgEfKq0rVwAAAABJRU5ErkJggg==")
    m = mail(frm="Service communication <communication@univ-reims.fr>", to=f"David <david@{DOMAIN}>", subject="Lettre d'information de novembre",
             body="Journée portes ouvertes le samedi 6 février.\n\nProgramme sur le site.", hours_ago=26)
    m.add_alternative('<html><body bgcolor="#f4f4f4"><script>document.title="x"</script>'
                      '<table width="600" align="center" cellpadding="16" style="background-color:#ffffff;font-family:Arial,sans-serif"><tr><td>'
                      '<img src="cid:logo@iut" alt="IUT" width="48" height="48"><h1 style="color:#0b4f6c">Journée portes ouvertes</h1>'
                      '<p>Samedi <b>6 février</b>, de 9h à 17h.</p><p><a href="https://www.univ-reims.fr/">Voir le programme</a></p>'
                      '<img src="https://images.example.org/bandeau.png" alt="Bandeau" width="560" height="120">'
                      '<img src="https://suivi.example.org/ouvert.gif" width="1" height="1">'
                      '</td></tr></table></body></html>', subtype="html")
    m.get_payload()[1].add_related(pixel, maintype="image", subtype="png", cid="<logo@iut>")
    return m


NEWSLETTER = _newsletter()
# Alias contact@ : le MÊME message (même Message-ID) arrive chez les quatre membres. Une seule demande (F-44).
ALIAS_ID = "<inscription-camille-1@gmail.com>"
ALIAS = mail(frm="Camille Roux <camille.roux@gmail.com>", to=f"contact@{DOMAIN}", subject="Question d'inscription",
             body="Bonjour, les inscriptions sont-elles encore ouvertes pour la rentrée de janvier ?", hours_ago=5, msgid=ALIAS_ID,
             extra={"Delivered-To": "{user}@" + DOMAIN})
ALIAS_USERS = ["david", "claire", "marc", "lea"]

# (utilisateur, dossier, message)
PLAN = [
    ("david", "Sent", SENT), ("david", "INBOX", REPLY), ("david", "Sent", AUTO_SENT), ("david", "INBOX", AUTO_REPLY), ("david", "INBOX", TASK_MAIL),
    ("david", "INBOX", AVAIL_MAIL), ("david", "INBOX", LONG_MAIL), ("david", "INBOX", INVITATION),
    ("david", "INBOX", NEWSLETTER),
] + [(u, "INBOX", ALIAS) for u in ALIAS_USERS]
