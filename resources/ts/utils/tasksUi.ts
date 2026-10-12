export const taskStatusLabels: Record<string, string> = {
  brouillon: 'Brouillon',
  imputee: 'Imputée',
  prise_en_charge: 'Prise en charge',
  en_cours: 'En cours',
  en_attente: 'En attente',
  terminee: 'Terminée',
  a_valider: 'À valider',
  validee: 'Validée',
  retournee: 'Retournée',
  annulee: 'Annulée',
}

export const taskStatusColor = (status?: string | null) => {
  switch (status) {
    case 'validee':
    case 'terminee':
      return 'success'
    case 'a_valider':
      return 'primary'
    case 'en_cours':
    case 'prise_en_charge':
      return 'info'
    case 'retournee':
    case 'annulee':
      return 'error'
    case 'en_attente':
    case 'imputee':
      return 'warning'
    default:
      return 'secondary'
  }
}

export const taskPriorityLabels: Record<string, string> = {
  normale: 'Normale',
  importante: 'Importante',
  urgente: 'Urgente',
  tres_urgente: 'Très urgente',
}

export const taskPriorityColor = (priority?: string | null) => {
  switch (priority) {
    case 'tres_urgente':
      return 'error'
    case 'urgente':
      return 'warning'
    case 'importante':
      return 'info'
    default:
      return 'secondary'
  }
}

export const instructionStatusLabels: Record<string, string> = {
  brouillon: 'Brouillon',
  a_faire: 'À faire',
  emise: 'Émise',
  prise_en_charge: 'Prise en charge',
  en_cours: 'En cours',
  en_attente: 'En attente',
  executee: 'Exécutée',
  en_validation: 'En validation',
  cloturee: 'Clôturée',
  annulee: 'Annulée',
}

export const productionStatusLabels: Record<string, string> = {
  a_produire: 'À produire',
  en_preparation: 'En préparation',
  deposee: 'Déposée',
  a_completer: 'À compléter',
  soumise_validation: 'Soumise à validation',
  en_validation: 'En validation',
  rejetee: 'Rejetée',
  validee: 'Validée',
  prete_a_transmettre: 'Prête à transmettre',
  transmise: 'Transmise',
  cloturee: 'Clôturée',
  annulee: 'Annulée',
}

export const productionFrequencyLabels: Record<string, string> = {
  once: 'Ponctuelle',
  daily: 'Quotidienne',
  business_days: 'Jours ouvrables',
  weekly: 'Hebdomadaire',
  weekdays: 'Jours de la semaine',
  biweekly: 'Toutes les deux semaines',
  semimonthly: 'Deux fois par mois',
  monthly: 'Mensuelle',
  quarterly: 'Trimestrielle',
  semiannual: 'Semestrielle',
  annual: 'Annuelle',
  specific_dates: 'Dates spécifiques',
  custom: 'Personnalisée',
}

export const productionDelayLabels: Record<string, string> = {
  non_deposee: 'Non déposée',
  deposee_en_retard: 'Déposée en retard',
  validee_en_retard: 'Validée en retard',
  transmise_en_retard: 'Transmise en retard',
  non_transmise: 'Non transmise',
}

export const productionStatusColor = (status?: string | null) => {
  switch (status) {
    case 'transmise':
    case 'validee':
    case 'cloturee':
      return 'success'
    case 'en_validation':
    case 'soumise_validation':
    case 'prete_a_transmettre':
      return 'primary'
    case 'deposee':
    case 'en_preparation':
      return 'info'
    case 'rejetee':
    case 'annulee':
      return 'error'
    case 'a_completer':
    case 'a_produire':
      return 'warning'
    default:
      return 'secondary'
  }
}

export const formatTaskDue = (due?: string | null) => {
  if (!due)
    return '—'
  try {
    return new Date(due).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    })
  }
  catch {
    return due
  }
}

export const taskKanbanColumnOrder = [
  'imputee',
  'prise_en_charge',
  'en_cours',
  'en_attente',
  'retournee',
  'a_valider',
  'terminee',
  'validee',
]

export const taskSourceLabels: Record<string, string> = {
  manual: 'Manuelle',
  courrier: 'Courrier',
  ticket: 'Ticket',
  meeting: 'Réunion',
  decision: 'Décision',
  parapheur: 'Parapheur',
  dossier: 'Dossier',
  affaire: 'Affaire',
  appointment: 'RDV',
  instruction: 'Instruction',
  other: 'Autre',
}

/** Avancement affiché : valeur stockée, plafonnée au minimum du statut workflow. */
export const taskProgressBaseline: Record<string, number> = {
  brouillon: 0,
  imputee: 10,
  prise_en_charge: 25,
  en_cours: 40,
  en_attente: 40,
  retournee: 45,
  terminee: 90,
  a_valider: 90,
  validee: 100,
  annulee: 0,
}

export const taskDisplayProgress = (status?: string | null, progress?: number | null) => {
  if (status === 'annulee')
    return 0
  const stored = Math.min(100, Math.max(0, Number(progress ?? 0)))
  const baseline = taskProgressBaseline[status || ''] ?? 0
  if (status === 'validee' || status === 'terminee')
    return Math.max(stored, baseline)

  return Math.max(stored, baseline)
}

export const taskProgressEditable = (status?: string | null) =>
  ['prise_en_charge', 'en_cours', 'en_attente', 'retournee'].includes(String(status || ''))

export const taskProgressColor = (value: number) => {
  if (value >= 90)
    return 'success'
  if (value >= 40)
    return 'primary'
  if (value >= 10)
    return 'info'

  return 'secondary'
}

