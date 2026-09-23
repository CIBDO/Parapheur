/**
 * Normalise une réponse API (tableau brut ou paginator Laravel `{ data: [] }`).
 */
export function listItems<T = any>(payload: any): T[] {
  if (Array.isArray(payload))
    return payload
  if (Array.isArray(payload?.data))
    return payload.data

  return []
}
