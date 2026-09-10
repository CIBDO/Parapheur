export const appointmentStatusColor = (status?: string | null): string => {
  switch (status) {
    case 'confirme':
    case 'valide':
    case 'pret':
      return 'success'
    case 'a_valider':
    case 'creneau_propose':
      return 'warning'
    case 'demande_recue':
    case 'a_examiner':
    case 'en_attente':
      return 'info'
    case 'en_cours':
      return 'primary'
    case 'refuse':
    case 'annule':
      return 'error'
    case 'reporte':
      return 'secondary'
    case 'suite_a_donner':
    case 'termine':
      return 'primary'
    case 'cloture':
    case 'archive':
      return 'default'
    default:
      return 'secondary'
  }
}

export const appointmentStatusLabel = (status?: string | null): string => {
  const map: Record<string, string> = {
    brouillon: 'Brouillon',
    demande_recue: 'Demande reçue',
    a_examiner: 'À examiner',
    en_attente: 'En attente',
    creneau_a_proposer: 'Créneau à proposer',
    creneau_propose: 'Créneau proposé',
    a_valider: 'À valider',
    valide: 'Validé',
    confirme: 'Confirmé',
    reporte: 'Reporté',
    refuse: 'Refusé',
    annule: 'Annulé',
    pret: 'Prêt',
    en_cours: 'En cours',
    termine: 'Terminé',
    suite_a_donner: 'Suite à donner',
    cloture: 'Clôturé',
    archive: 'Archivé',
  }

  return status ? (map[status] || status) : '—'
}

export const calendarEventColor = (source?: string, masked?: boolean): string => {
  if (masked)
    return 'secondary'
  switch (source) {
    case 'appointment':
      return 'primary'
    case 'meeting':
      return 'info'
    case 'unavailability':
      return 'warning'
    default:
      return 'secondary'
  }
}

export const formatAppointmentSlot = (startAt?: string | null, endAt?: string | null): string => {
  if (!startAt)
    return '—'
  const start = new Date(startAt)
  const end = endAt ? new Date(endAt) : null
  const date = start.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
  const time = start.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
  const endTime = end ? end.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : null

  return endTime ? `${date} · ${time} – ${endTime}` : `${date} · ${time}`
}
