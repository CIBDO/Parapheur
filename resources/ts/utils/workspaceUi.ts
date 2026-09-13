export const workspaceTypeLabels: Record<string, string> = {
  personal: 'Personnel',
  shared: 'Partagé',
  team: 'Équipe',
  project: 'Projet',
}

export const workspaceMemberRoleLabels: Record<string, string> = {
  owner: 'Propriétaire',
  manager: 'Gestionnaire',
  editor: 'Éditeur',
  contributor: 'Contributeur',
  viewer: 'Lecteur',
}

export const workspaceShareAbilityLabels: Record<string, string> = {
  view: 'Lecture',
  contribute: 'Contribution',
  edit: 'Modification',
  manage: 'Gestion',
}

export function formatBytes(bytes: number | null | undefined): string {
  const n = Number(bytes || 0)
  if (n < 1024)
    return `${n} o`
  if (n < 1024 ** 2)
    return `${(n / 1024).toFixed(1)} Ko`
  if (n < 1024 ** 3)
    return `${(n / (1024 ** 2)).toFixed(1)} Mo`

  return `${(n / (1024 ** 3)).toFixed(2)} Go`
}

export function storagePercent(used: number, quota: number): number {
  if (!quota || quota <= 0)
    return 0

  return Math.min(100, Math.round((used / quota) * 100))
}
