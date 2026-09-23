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
  en_cours: 'En cours',
  executee: 'Exécutée',
  cloturee: 'Clôturée',
  annulee: 'Annulée',
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
