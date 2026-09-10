/** Libellés UI — module Réunions */

export const meetingStatusLabels: Record<string, string> = {
  brouillon: 'Brouillon',
  en_preparation: 'En préparation',
  convocation_a_valider: 'Convocation à valider',
  planifiee: 'Planifiée',
  convoquee: 'Convoquée',
  confirmation_en_cours: 'Confirmation en cours',
  prete: 'Prête',
  en_cours: 'En cours',
  suspendue: 'Suspendue',
  terminee: 'Terminée',
  cr_en_redaction: 'CR en rédaction',
  cr_en_validation: 'CR en validation',
  cr_valide: 'CR validé',
  cloturee: 'Clôturée',
  annulee: 'Annulée',
  reportee: 'Reportée',
  archivee: 'Archivée',
}

export const meetingStatusColor = (status?: string) => {
  if (['en_cours'].includes(status || ''))
    return 'info'
  if (['cr_valide', 'cloturee', 'prete', 'archivee'].includes(status || ''))
    return 'success'
  if (['annulee'].includes(status || ''))
    return 'error'
  if (['reportee', 'suspendue', 'convocation_a_valider', 'cr_en_validation', 'en_retard'].includes(status || ''))
    return 'warning'

  return 'secondary'
}

export const decisionStatusLabels: Record<string, string> = {
  a_faire: 'À faire',
  planifiee: 'Planifiée',
  en_cours: 'En cours',
  en_attente: 'En attente',
  bloquee: 'Bloquée',
  executee: 'Exécutée',
  partiellement_executee: 'Partiellement exécutée',
  en_retard: 'En retard',
  annulee: 'Annulée',
  cloturee: 'Clôturée',
}

export const documentKindLabels: Record<string, string> = {
  document_principal: 'Document principal',
  piece_jointe: 'Pièce jointe',
  annexe: 'Annexe',
  presentation: 'Présentation',
  rapport: 'Rapport',
  tableau: 'Tableau',
  document_de_travail: 'Document de travail',
  compte_rendu: 'Compte rendu',
  pv: 'Procès-verbal',
  convocation: 'Convocation',
  autre: 'Autre',
}

export const attendanceLabels: Record<string, string> = {
  present: 'Présent',
  absent: 'Absent',
  excuse: 'Excusé',
  represente: 'Représenté',
  invite: 'Invité',
}

export const participationTypeLabels: Record<string, string> = {
  interne: 'Interne',
  externe: 'Externe',
}

export const exportKindLabels: Record<string, string> = {
  convocation: 'Convocation',
  agenda: 'Ordre du jour',
  attendance: 'Liste de présence',
  decisions: 'Relevé de décisions',
}

/** Ouvre un export HTML authentifié (cookie Sanctum) dans un nouvel onglet. */
export async function openMeetingHtml(url: string, opts?: { print?: boolean }) {
  const res = await fetch(url, {
    credentials: 'include',
    headers: {
      Accept: 'text/html',
      'X-Requested-With': 'XMLHttpRequest',
    },
  })
  if (!res.ok)
    throw new Error('Impossible d’ouvrir le document')

  const html = await res.text()
  const blob = new Blob([html], { type: 'text/html;charset=utf-8' })
  const objectUrl = URL.createObjectURL(blob)
  const win = window.open(objectUrl, '_blank')
  if (opts?.print && win) {
    win.addEventListener('load', () => {
      try {
        win.focus()
        win.print()
      }
      catch {
        // ignore
      }
    })
  }
  setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000)
}

/** Télécharge un HTML comme fichier (équivalent « PDF » projet = HTML institutionnel). */
export async function downloadMeetingHtml(url: string, filename: string) {
  const res = await fetch(url, {
    credentials: 'include',
    headers: {
      Accept: 'text/html',
      'X-Requested-With': 'XMLHttpRequest',
    },
  })
  if (!res.ok)
    throw new Error('Téléchargement impossible')

  const blob = await res.blob()
  const objectUrl = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = objectUrl
  a.download = filename.endsWith('.html') ? filename : `${filename}.html`
  a.click()
  URL.revokeObjectURL(objectUrl)
}
