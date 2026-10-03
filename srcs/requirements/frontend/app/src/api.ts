export type SortDir = 'asc' | 'desc'

export type Sort = { columnId: string; dir: SortDir }

export type FilterOp = 'contains' | 'equals' | 'eq' | 'gt' | 'lt'

export type Filter = { columnId: string; op: FilterOp; value: string | number }

// Shapes returned by the NestJS API
export type ColumnType = 'text' | 'number' | 'date' | 'phone'

export type Column = {
  id: string
  name: string
  type: ColumnType
  position: number
}

export type CellValue = string | number | null

// values is keyed by column id: { "<columnId>": value }
export type Contact = {
  id: number
  values: Record<string, CellValue>
}

export type ContactPage = {
  items: Contact[]
  nextOffset: number | null
}

const PAGE_SIZE = 50

// Reads the "message" of a NestJS error body (a string or a list of issues)
async function errorMessage(response: Response): Promise<string> {
  const body: unknown = await response.json().catch(() => null)
  if (body && typeof body === 'object' && 'message' in body) {
    const { message } = body
    if (typeof message === 'string') {
      return message
    }
    if (Array.isArray(message)) {
      return message
        .map((issue) =>
          issue && typeof issue === 'object' && 'message' in issue
            ? String(issue.message)
            : String(issue),
        )
        .join(', ')
    }
  }
  return `HTTP ${response.status}`
}

// Relative URLs: the same code works behind the Vite proxy (dev) and nginx (prod)
async function request<T>(url: string, init?: RequestInit): Promise<T> {
  const response = await fetch(url, init)
  if (!response.ok) {
    throw new Error(await errorMessage(response))
  }
  // 204 No Content (DELETE) has no body to parse
  return (response.status === 204 ? undefined : await response.json()) as T
}

function getJson<T>(url: string): Promise<T> {
  return request<T>(url)
}

function sendJson<T>(method: string, url: string, body: unknown): Promise<T> {
  return request<T>(url, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  })
}

export function fetchColumns(): Promise<Column[]> {
  return getJson<Column[]>('/api/columns')
}

export function fetchContactsPage(
  offset: number,
  sort: Sort | null,
  filters: Filter[],
): Promise<ContactPage> {
  const params = new URLSearchParams({
    limit: String(PAGE_SIZE),
    offset: String(offset),
  })
  if (sort) {
    params.set('sort', sort.columnId)
    params.set('dir', sort.dir)
  }
  if (filters.length > 0) {
    params.set('filters', JSON.stringify(filters))
  }
  return getJson<ContactPage>(`/api/contacts?${params.toString()}`)
}

export function createContact(values: Record<string, CellValue>): Promise<Contact> {
  return sendJson<Contact>('POST', '/api/contacts', { values })
}

// Sends only the changed cells; the API merges them into the contact
export function updateContact(
  id: number,
  values: Record<string, CellValue>,
): Promise<Contact> {
  return sendJson<Contact>('PATCH', `/api/contacts/${id}`, { values })
}

export function deleteContact(id: number): Promise<void> {
  return request<void>(`/api/contacts/${id}`, { method: 'DELETE' })
}