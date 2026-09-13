/** Helpers UI module GED */

export {
  actionLabels,
  confidentialityLabels,
  confidentialityOptions,
  expectedActionOptions,
  formatDateFr,
  labelOf,
  priorityColor,
  priorityLabels,
  priorityOptions,
  statusColor,
  statusLabels,
  statusOptions,
} from '@/utils/parapheurUi'

export const archiveStatusLabels: Record<string, string> = {
  actif: 'Actif',
  a_archiver: 'À archiver',
  archive: 'Archivé',
  gele: 'Gelé',
  a_verser: 'À verser',
  verse: 'Versé',
}

export const originLabels: Record<string, string> = {
  parapheur: 'Parapheur',
  ged: 'GED',
  meeting: 'Réunion',
  appointment: 'Rendez-vous',
  instruction: 'Instruction',
}

export const attachmentKindLabels: Record<string, string> = {
  piece_jointe: 'Pièce jointe',
  annexe: 'Annexe',
  complement: 'Complément',
  justificatif: 'Justificatif',
  reference: 'Référence',
  final: 'Document final',
}

export const linkRelationLabels: Record<string, string> = {
  related_to: 'Lié à',
  replaces: 'Remplace',
  supersedes: 'Rend obsolète',
  annex_of: 'Annexe de',
  response_to: 'Réponse à',
  generated_from: 'Généré depuis',
  version_of: 'Version de',
}

export const archiveStatusOptions = Object.entries(archiveStatusLabels).map(([value, title]) => ({ value, title }))
export const originOptions = Object.entries(originLabels).map(([value, title]) => ({ value, title }))
