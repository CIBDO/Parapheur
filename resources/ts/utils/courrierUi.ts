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

export function formatCorrespondenceNumber(item: { arrival_number?: string | null; departure_number?: string | null; id: number }) {
  return item.arrival_number || item.departure_number || `#${item.id}`
}

export function statusLabel(status?: string | null) {
  if (!status)
    return '—'
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

