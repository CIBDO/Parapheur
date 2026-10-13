/** Helpers UI Documentation institutionnelle / GED */

export const archiveStatusLabels: Record<string, string> = {
  actif: 'Actif',
  a_archiver: 'À archiver',
  archive: 'Archivé',
  gele: 'Gelé',
  a_verser: 'À verser',
  verse: 'Versé',
}

export const publicationStatusLabels: Record<string, string> = {
  brouillon: 'Brouillon',
  depose: 'Déposé',
  en_verification: 'En vérification',
  a_corriger: 'À corriger',
  valide: 'Validé',
  publie: 'Publié',
  archive: 'Archivé',
  rejete: 'Rejeté',
}

export const publicationStatusColor: Record<string, string> = {
  brouillon: 'secondary',
  depose: 'info',
  en_verification: 'warning',
  a_corriger: 'orange',
  valide: 'primary',
  publie: 'success',
  archive: 'secondary',
  rejete: 'error',
}

export const publicationWorkflowLabels: Record<string, string> = {
  simple: 'Circuit simple',
  controlled: 'Circuit contrôlé',
  hierarchical: 'Circuit hiérarchique',
}

export const originLabels: Record<string, string> = {
  parapheur: 'Parapheur',
  ged: 'Documentation institutionnelle',
  meeting: 'Réunion',
  appointment: 'Rendez-vous',
  instruction: 'Instruction',
  courrier: 'Courrier',
  task: 'Tâche',
  production: 'Production',
  personal: 'Espace personnel',
  workspace: 'Espace collaboratif',
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
export const publicationStatusOptions = Object.entries(publicationStatusLabels).map(([value, title]) => ({ value, title }))
export const publicationWorkflowOptions = Object.entries(publicationWorkflowLabels).map(([value, title]) => ({ value, title }))
