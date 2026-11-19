export type QcStatus = 'ok' | 'warn' | 'error' | 'pending'

export type QcResult = {
  status: QcStatus
  notes: string[]
  checksum?: string
  width?: number
  height?: number
}

const MAX_BYTES = 20 * 1024 * 1024
const MIN_IMAGE_PX = 800
const WARN_SMALL_BYTES = 15 * 1024

export async function sha256Hex(file: File): Promise<string> {
  const buf = await file.arrayBuffer()
  const hash = await crypto.subtle.digest('SHA-256', buf)
  return [...new Uint8Array(hash)].map(b => b.toString(16).padStart(2, '0')).join('')
}

export async function runQc(file: File): Promise<QcResult> {
  const notes: string[] = []
  let status: QcStatus = 'ok'
  let width: number | undefined
  let height: number | undefined

  if (file.size <= 0) {
    return { status: 'error', notes: ['Fichier vide'] }
  }
  if (file.size > MAX_BYTES) {
    return { status: 'error', notes: ['Dépasse 20 Mo'] }
  }
  if (file.size < WARN_SMALL_BYTES && file.type.startsWith('image/')) {
    notes.push('Fichier image très léger — vérifier la qualité / DPI')
    status = 'warn'
  }

  if (file.type.startsWith('image/')) {
    try {
      const dims = await readImageSize(file)
      width = dims.width
      height = dims.height
      if (width < MIN_IMAGE_PX && height < MIN_IMAGE_PX) {
        notes.push(`Résolution faible (${width}×${height}) — viser ≥ ${MIN_IMAGE_PX} px`)
        status = status === 'error' ? 'error' : 'warn'
      }
      const avg = await averageLuma(file)
      if (avg !== null && (avg > 245 || avg < 12)) {
        notes.push(avg > 245 ? 'Page potentiellement blanche' : 'Page potentiellement trop sombre')
        status = status === 'error' ? 'error' : 'warn'
      }
    }
    catch {
      notes.push('Impossible d’analyser l’image')
      status = status === 'error' ? 'error' : 'warn'
    }
  }

  let checksum: string | undefined
  try {
    checksum = await sha256Hex(file)
  }
  catch {
    notes.push('Checksum indisponible')
  }

  if (!notes.length)
    notes.push('Contrôle OK')

  return { status, notes, checksum, width, height }
}

function readImageSize(file: File): Promise<{ width: number, height: number }> {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file)
    const img = new Image()
    img.onload = () => {
      resolve({ width: img.naturalWidth, height: img.naturalHeight })
      URL.revokeObjectURL(url)
    }
    img.onerror = () => {
      URL.revokeObjectURL(url)
      reject(new Error('image'))
    }
    img.src = url
  })
}

function averageLuma(file: File): Promise<number | null> {
  return new Promise((resolve) => {
    const url = URL.createObjectURL(file)
    const img = new Image()
    img.onload = () => {
      try {
        const canvas = document.createElement('canvas')
        const w = Math.min(64, img.naturalWidth)
        const h = Math.min(64, img.naturalHeight)
        canvas.width = w
        canvas.height = h
        const ctx = canvas.getContext('2d')
        if (!ctx) {
          resolve(null)
          return
        }
        ctx.drawImage(img, 0, 0, w, h)
        const data = ctx.getImageData(0, 0, w, h).data
        let sum = 0
        let n = 0
        for (let i = 0; i < data.length; i += 4) {
          sum += 0.2126 * data[i] + 0.7152 * data[i + 1] + 0.0722 * data[i + 2]
          n++
        }
        resolve(n ? sum / n : null)
      }
      catch {
        resolve(null)
      }
      finally {
        URL.revokeObjectURL(url)
      }
    }
    img.onerror = () => {
      URL.revokeObjectURL(url)
      resolve(null)
    }
    img.src = url
  })
}
