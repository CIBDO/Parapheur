export const correspondenceDirectionLabels: Record<string, string> = {
  entrant: 'Entrant',
  sortant: 'Sortant',
  interne: 'Interne',
}

export const correspondenceDirectionColors: Record<string, string> = {
  entrant: 'info',
  sortant: 'success',
  interne: 'warning',
}

export const correspondenceStatusLabels: Record<string, string> = {
  recu: 'Reçu',
  enregistre: 'Enregistré',
  a_qualifier: 'À qualifier',
  a_affecter: 'À affecter',
  affecte: 'Affecté',
  pris_en_charge: 'Pris en charge',
  en_traitement: 'En traitement',
  en_attente: 'En attente',
  en_attente_complement: 'En attente de complément',
  projet_reponse: 'Projet de réponse',
  a_viser: 'À viser',
  a_valider: 'À valider',
  a_signer: 'À signer',
  valide: 'Validé',
  signe: 'Signé',
  repondu: 'Répondu',
  a_expedier: 'À expédier',
  expedie: 'Expédié',
  accuse_recu: 'Accusé reçu',
  classe: 'Classé',
  archive: 'Archivé',
  annule: 'Annulé',
}

export const correspondenceStatusColors: Record<string, string> = {
  recu: 'secondary',
  enregistre: 'info',
  a_qualifier: 'secondary',
  a_affecter: 'info',
  affecte: 'primary',
  pris_en_charge: 'primary',
  en_traitement: 'warning',
  en_attente: 'default',
  en_attente_complement: 'default',
  projet_reponse: 'warning',
  a_viser: 'purple',
  a_valider: 'purple',
  a_signer: 'purple',
  valide: 'success',
  signe: 'success',
  repondu: 'success',
  a_expedier: 'orange',
  expedie: 'teal',
  accuse_recu: 'success',
  classe: 'default',
  archive: 'default',
  annule: 'error',
}

export const correspondencePriorityLabels: Record<string, string> = {
  normale: 'Normale',
  importante: 'Importante',
  urgente: 'Urgente',
  tres_urgente: 'Très urgente',
}

export const correspondencePriorityColors: Record<string, string> = {
  normale: 'info',
  importante: 'warning',
  urgente: 'error',
  tres_urgente: 'error',
}

export const correspondenceConfidentialityLabels: Record<string, string> = {
  normal: 'Normal',
  restreint: 'Restreint',
  confidentiel: 'Confidentiel',
  tres_confidentiel: 'Très confidentiel',
}

export const correspondenceMediumLabels: Record<string, string> = {
  physique: 'Physique',
  electronique: 'Électronique',
  hybride: 'Hybride',
}

export function formatCorrespondenceNumber(item: {
  arrival_number?: string | null
  departure_number?: string | null
  direction?: string | null
  id: number
  status?: string | null
}) {
  if (item.direction === 'sortant') {
    if (item.departure_number)
      return item.departure_number
    return item.status === 'projet_reponse' ? `Projet #${item.id}` : `#${item.id}`
  }

  return item.arrival_number || item.departure_number || `#${item.id}`
}

/** Affiche une date au format fr-FR (jj/mm/aaaa). */
export function formatCourrierDate(date?: string | null) {
  if (!date)
    return '—'
  const d = new Date(date)
  if (Number.isNaN(d.getTime()))
    return '—'

  return d.toLocaleDateString('fr-FR')
}

/** Affiche date + heure au format fr-FR. */
export function formatCourrierDateTime(date?: string | null) {
  if (!date)
    return '—'
  const d = new Date(date)
  if (Number.isNaN(d.getTime()))
    return '—'

  return d.toLocaleString('fr-FR')
}

export function statusLabel(status?: string | null, direction?: string | null) {
  if (!status)
    return '—'
  if (direction === 'sortant' && status === 'projet_reponse')
    return 'Projet de départ'
  if (direction === 'sortant' && status === 'enregistre')
    return 'Enregistré au départ'

  return correspondenceStatusLabels[status] ?? status
}

export function directionLabel(direction?: string | null) {
  if (!direction)
    return '—'
  return correspondenceDirectionLabels[direction] ?? direction
}

export const getCorrespondenceStatusLabel = statusLabel
export const getCorrespondenceStatusColor = (status?: string | null) => correspondenceStatusColors[status || ''] || 'default'
export const getCorrespondencePriorityLabel = (priority?: string | null) => correspondencePriorityLabels[priority || ''] || priority || '—'
export const getCorrespondencePriorityIcon = () => 'tabler-flag'
export const getCorrespondenceConfidentialityLabel = (value?: string | null) => correspondenceConfidentialityLabels[value || ''] || value || '—'
export const getCorrespondenceDirectionLabel = directionLabel

export const documentTemplateKindLabels: Record<string, string> = {
  bordereau_transmission: 'Bordereau de transmission',
  bordereau_envoi: 'Bordereau d\'envoi',
  fiche_circulation: 'Fiche de circulation',
  accuse_reception: 'Accusé de réception',
  lettre: 'Lettre',
  note: 'Note',
  formulaire: 'Formulaire',
  autre: 'Autre',
}

export const getTemplateCategoryLabel = (kind?: string | null) => documentTemplateKindLabels[kind || ''] || kind || '—'
export const getTemplateKindLabel = getTemplateCategoryLabel

export const transmissionSlipStatusLabels: Record<string, string> = {
  brouillon: 'Brouillon',
  genere: 'Généré',
  en_modification: 'En modification',
  a_valider: 'À valider',
  valide: 'Validé',
  imprime: 'Imprimé',
  transmis: 'Transmis',
  recu: 'Reçu',
  retourne: 'Retourné',
  cloture: 'Clôturé',
  annule: 'Annulé',
}

export const transmissionSlipStatusColors: Record<string, string> = {
  brouillon: 'secondary',
  genere: 'info',
  en_modification: 'warning',
  a_valider: 'warning',
  valide: 'primary',
  imprime: 'teal',
  transmis: 'success',
  recu: 'success',
  retourne: 'orange',
  cloture: 'default',
  annule: 'error',
}

export const transmissionSlipNatureLabels: Record<string, string> = {
  pour_traitement: 'Pour traitement',
  pour_information: 'Pour information',
  pour_avis: 'Pour avis',
  pour_signature: 'Pour signature',
  retour: 'Retour',
  autre: 'Autre',
}

export function getTransmissionSlipStatusLabel(status?: string | null) {
  return transmissionSlipStatusLabels[status || ''] || status || '—'
}

export function getTransmissionSlipStatusColor(status?: string | null) {
  return transmissionSlipStatusColors[status || ''] || 'default'
}

export function getTransmissionSlipNatureLabel(nature?: string | null) {
  if (!nature)
    return '—'
  return transmissionSlipNatureLabels[nature] || nature
}

export function detailRouteName(direction?: string | null) {
  return matchDirection(direction)
}

function matchDirection(direction?: string | null) {
  if (direction === 'sortant')
    return 'courrier-sortants-id'
  if (direction === 'interne')
    return 'courrier-internes-id'
  return 'courrier-entrants-id'
}

export function listItems<T = any>(payload: any): T[] {
  if (Array.isArray(payload))
    return payload
  if (Array.isArray(payload?.data))
    return payload.data
  return []
}

export const partyRoleLabels: Record<string, string> = {
  from: 'Expéditeur',
  to: 'Destinataire',
  cc: 'Copie',
  ampliation: 'Ampliation',
  info: 'Pour information',
}

export const partyRoleColors: Record<string, string> = {
  from: 'info',
  to: 'primary',
  cc: 'secondary',
  ampliation: 'warning',
  info: 'default',
}

export const assignmentStatusLabels: Record<string, string> = {
  transmis: 'Transmis',
  recu: 'Reçu',
  consulte: 'Consulté',
  pris_en_charge: 'Pris en charge',
  traite: 'Traité',
  retourne: 'Retourné',
  reoriente: 'Réorienté',
}

export const assignmentStatusColors: Record<string, string> = {
  transmis: 'info',
  recu: 'primary',
  consulte: 'secondary',
  pris_en_charge: 'warning',
  traite: 'success',
  retourne: 'error',
  reoriente: 'orange',
}

export function partyRoleLabel(role?: string | null) {
  return partyRoleLabels[role || ''] || role || '—'
}

export function partyRoleValue(party: { role?: string | { value?: string } | null } | null | undefined): string {
  if (!party?.role)
    return ''
  return typeof party.role === 'string' ? party.role : (party.role.value || '')
}

export function partyDisplayName(party: any): string {
  return party?.name || party?.correspondent?.name || ''
}

export function partiesByRole(parties: any[] | undefined, role: string) {
  return (parties || []).filter(party => partyRoleValue(party) === role)
}

export function assignmentStatusLabel(status?: string | null) {
  return assignmentStatusLabels[status || ''] || status || '—'
}

