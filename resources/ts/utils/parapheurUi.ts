/** Libellés et helpers UI partagés — charte E-Tresor / DGTCP */

export const statusLabels: Record<string, string> = {
  brouillon: 'Brouillon',
  depose: 'Déposé',
  en_circuit: 'En circuit',
  transmis: 'Transmis',
  a_consulter: 'À consulter',
  en_consultation: 'En consultation',
  en_attente: 'En attente',
  a_corriger: 'À corriger',
  corrige: 'Corrigé',
  a_viser: 'À viser',
  vise: 'Visé',
  a_valider: 'À valider',
  valide: 'Validé',
  rejete: 'Rejeté',
  traite: 'Traité',
  classe: 'Classé',
  archive: 'Archivé',
  annule: 'Annulé',
}

export const priorityLabels: Record<string, string> = {
  normale: 'Normale',
  importante: 'Importante',
  urgente: 'Urgente',
  tres_urgente: 'Très urgente',
}

export const confidentialityLabels: Record<string, string> = {
  normal: 'Normal',
  restreint: 'Restreint',
  confidentiel: 'Confidentiel',
  tres_confidentiel: 'Très confidentiel',
}

export const actionLabels: Record<string, string> = {
  information: 'Pour information',
  consultation: 'Pour consultation',
  avis: 'Pour avis',
  observations: 'Pour observations',
  instruction: 'Pour instruction',
  visa: 'Pour visa',
  validation: 'Pour validation',
  revision: 'Pour révision',
  signature: 'Pour signature',
}

export const folderMeta: Array<{
  key: string
  title: string
  icon: string
  color: string
}> = [
  { key: 'a_traiter', title: 'À traiter', icon: 'tabler-inbox', color: 'primary' },
  { key: 'a_consulter', title: 'À consulter', icon: 'tabler-eye', color: 'info' },
  { key: 'pour_information', title: 'Pour information', icon: 'tabler-info-circle', color: 'secondary' },
  { key: 'a_viser', title: 'À viser', icon: 'tabler-stamp', color: 'warning' },
  { key: 'a_valider', title: 'À valider', icon: 'tabler-circle-check', color: 'success' },
  { key: 'en_attente', title: 'En attente', icon: 'tabler-clock-pause', color: 'warning' },
  { key: 'retournes', title: 'Retournés', icon: 'tabler-arrow-back-up', color: 'error' },
  { key: 'envoyes', title: 'Envoyés', icon: 'tabler-send', color: 'primary' },
  { key: 'traites', title: 'Traités', icon: 'tabler-checks', color: 'success' },
  { key: 'archives', title: 'Archivés', icon: 'tabler-archive', color: 'secondary' },
]

export const statusOptions = Object.entries(statusLabels).map(([value, title]) => ({ value, title }))
export const priorityOptions = Object.entries(priorityLabels).map(([value, title]) => ({ value, title }))
export const confidentialityOptions = Object.entries(confidentialityLabels).map(([value, title]) => ({ value, title }))
export const expectedActionOptions = Object.entries(actionLabels).map(([value, title]) => ({ value, title }))

export const labelOf = (map: Record<string, string>, value?: string | null, fallback = '—') => {
  if (!value)
    return fallback

  return map[value] || value
}

export const statusColor = (status?: string) => {
  if (['valide', 'vise', 'traite', 'classe', 'archive'].includes(status || ''))
    return 'success'
  if (['rejete', 'annule', 'a_corriger'].includes(status || ''))
    return 'error'
  if (['en_attente', 'a_valider', 'a_viser'].includes(status || ''))
    return 'warning'
  if (['a_consulter', 'en_circuit', 'transmis', 'en_consultation'].includes(status || ''))
    return 'info'

  return 'secondary'
}

export const priorityColor = (priority?: string) => {
  if (priority === 'tres_urgente' || priority === 'urgente')
    return 'error'
  if (priority === 'importante')
    return 'warning'

  return 'secondary'
}

export const formatDateFr = (value?: string | null) => {
  if (!value)
    return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime()))
    return '—'

  return date.toLocaleDateString('fr-FR')
}

export const formatDateTimeFr = (value?: string | null) => {
  if (!value)
    return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime()))
    return '—'

  return date.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })
}
