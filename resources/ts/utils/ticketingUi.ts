export const ticketStatusLabels: Record<string, string> = {
  NOUVEAU: 'Nouveau',
  A_QUALIFIER: 'À qualifier',
  AFFECTE: 'Affecté',
  PRIS_EN_CHARGE: 'Pris en charge',
  EN_COURS: 'En cours',
  EN_ATTENTE_DEMANDEUR: 'En attente demandeur',
  EN_ATTENTE_TIERS: 'En attente tiers',
  ESCALADE: 'Escaladé',
  EN_ATTENTE_VALIDATION: 'En attente validation',
  RESOLU: 'Résolu',
  A_VALIDER: 'À valider',
  REOUVERT: 'Réouvert',
  CLOTURE: 'Clôturé',
  ANNULE: 'Annulé',
}

export const ticketStatusColors: Record<string, string> = {
  NOUVEAU: 'info',
  A_QUALIFIER: 'secondary',
  AFFECTE: 'primary',
  PRIS_EN_CHARGE: 'primary',
  EN_COURS: 'warning',
  EN_ATTENTE_DEMANDEUR: 'default',
  EN_ATTENTE_TIERS: 'default',
  ESCALADE: 'error',
  EN_ATTENTE_VALIDATION: 'warning',
  RESOLU: 'success',
  A_VALIDER: 'teal',
  REOUVERT: 'orange',
  CLOTURE: 'secondary',
  ANNULE: 'error',
}

export const ticketPriorityLabels: Record<string, string> = {
  P1: 'Critique',
  P2: 'Haute',
  P3: 'Moyenne',
  P4: 'Basse',
  critique: 'Critique',
  haute: 'Haute',
  moyenne: 'Moyenne',
  basse: 'Basse',
  low: 'Basse',
  medium: 'Moyenne',
  high: 'Haute',
  critical: 'Critique',
}

export const ticketPriorityColors: Record<string, string> = {
  P1: 'error',
  P2: 'warning',
  P3: 'info',
  P4: 'secondary',
  critique: 'error',
  haute: 'warning',
  moyenne: 'info',
  basse: 'secondary',
  low: 'secondary',
  medium: 'info',
  high: 'warning',
  critical: 'error',
}

/** Affiche le numéro ticket en remplaçant / par - */
export function formatTicketNumber(item: { number?: string | null; id?: number } | string | null | undefined) {
  if (item == null)
    return '—'
  const raw = typeof item === 'string' ? item : (item.number || (item.id != null ? `#${item.id}` : ''))
  if (!raw)
    return '—'

  return String(raw).replace(/\//g, '-')
}

export function ticketStatusLabel(status?: string | null) {
  if (!status)
    return '—'

  return ticketStatusLabels[status] || status
}

export function ticketStatusColor(status?: string | null) {
  if (!status)
    return 'default'

  return ticketStatusColors[status] || 'default'
}

export function ticketPriorityLabel(priority?: { code?: string; name?: string } | string | null) {
  if (!priority)
    return '—'
  if (typeof priority === 'string')
    return ticketPriorityLabels[priority] || priority
  if (priority.name)
    return priority.name
  if (priority.code)
    return ticketPriorityLabels[priority.code] || priority.code

  return '—'
}

export function ticketPriorityColor(priority?: { code?: string; level?: number } | string | null) {
  if (!priority)
    return 'default'
  const code = typeof priority === 'string' ? priority : priority.code
  if (code && ticketPriorityColors[code])
    return ticketPriorityColors[code]
  const level = typeof priority === 'object' ? priority.level : undefined
  if (level != null) {
    if (level <= 1)
      return 'error'
    if (level === 2)
      return 'warning'
    if (level === 3)
      return 'info'

    return 'secondary'
  }

  return 'default'
}

export function formatTicketDate(date?: string | null) {
  if (!date)
    return '—'
  const d = new Date(date)
  if (Number.isNaN(d.getTime()))
    return '—'

  return d.toLocaleDateString('fr-FR')
}

export function formatTicketDateTime(date?: string | null) {
  if (!date)
    return '—'
  const d = new Date(date)
  if (Number.isNaN(d.getTime()))
    return '—'

  return d.toLocaleString('fr-FR')
}

/** Libellés FR pour les rôles d’acteurs ticket. */
export function ticketActorRoleLabel(role: string | null | undefined): string {
  const map: Record<string, string> = {
    requester: 'Demandeur',
    assignee: 'Assigné',
    observer: 'Observateur',
    approver: 'Approbateur',
    writer: 'Rédacteur',
    supplier: 'Fournisseur',
  }
  if (!role)
    return '—'

  return map[role] || map[String(role).toLowerCase()] || String(role)
}

/**
 * Agents d’une équipe pour les selects (évite d’afficher tous les users → 422).
 * Compare les ids en Number pour éviter les mismatches string/number du VSelect.
 */
export function teamAgentSelectItems(
  teams: any[] | null | undefined,
  teamId: number | string | null | undefined,
): { value: number; title: string }[] {
  if (teamId === null || teamId === undefined || teamId === '')
    return []

  const tid = Number(teamId)
  if (!Number.isFinite(tid))
    return []

  const team = (teams || []).find(t => Number(t.id) === tid)
  if (!team)
    return []

  const members = team.members || team.users || []

  return members
    .map((m: any) => {
      const user = m?.user || m
      const id = Number(user?.id ?? m?.user_id)
      const name = user?.name || m?.name
      if (!Number.isFinite(id) || !name)
        return null

      return { value: id, title: String(name) }
    })
    .filter(Boolean) as { value: number; title: string }[]
}

export const ticketKanbanColumnOrder = [
  'NOUVEAU',
  'A_QUALIFIER',
  'EN_ATTENTE_VALIDATION',
  'AFFECTE',
  'PRIS_EN_CHARGE',
  'EN_COURS',
  'EN_ATTENTE_DEMANDEUR',
  'EN_ATTENTE_TIERS',
  'ESCALADE',
  'RESOLU',
  'A_VALIDER',
  'REOUVERT',
]

export function slaBadge(sla: any): { label: string; color: string } {
  if (!sla)
    return { label: 'Sans SLA', color: 'default' }
  if (sla.response_breached_at || sla.resolution_breached_at)
    return { label: 'SLA dépassé', color: 'error' }
  if (sla.warning_sent_at)
    return { label: 'SLA alerte', color: 'warning' }

  return { label: 'SLA OK', color: 'success' }
}
