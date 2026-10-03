import {
  useMutation,
  useQueryClient,
  type InfiniteData,
} from '@tanstack/react-query'
import {
  createContact,
  deleteContact,
  updateContact,
  type CellValue,
  type Contact,
  type ContactPage,
} from './api'

type Pages = InfiniteData<ContactPage, number>

// Create, update and delete write the API response straight into every
// cached contacts list (all sorts and filters) instead of refetching:
// an edited row keeps its place, even in a column it is sorted by.
export function useContactMutations() {
  const queryClient = useQueryClient()

  const updateCache = (change: (pages: Pages) => Pages) => {
    queryClient.setQueriesData<Pages>({ queryKey: ['contacts'] }, (old) =>
      old ? change(old) : old,
    )
  }

  const mapItems = (pages: Pages, map: (items: Contact[]) => Contact[]) => ({
    ...pages,
    pages: pages.pages.map((page) => ({ ...page, items: map(page.items) })),
  })

  const update = useMutation({
    mutationFn: ({ id, values }: { id: number; values: Record<string, CellValue> }) =>
      updateContact(id, values),
    onSuccess: (updated) =>
      updateCache((pages) =>
        mapItems(pages, (items) =>
          items.map((contact) => (contact.id === updated.id ? updated : contact)),
        ),
      ),
  })

  const create = useMutation({
    mutationFn: () => createContact({}),
    // The new (empty) row is shown at the top, where the user is looking
    onSuccess: (created) =>
      updateCache((pages) => ({
        ...pages,
        pages: pages.pages.map((page, index) =>
          index === 0 ? { ...page, items: [created, ...page.items] } : page,
        ),
      })),
  })

  const remove = useMutation({
    mutationFn: (id: number) => deleteContact(id),
    onSuccess: (_, id) =>
      updateCache((pages) =>
        mapItems(pages, (items) => items.filter((contact) => contact.id !== id)),
      ),
  })

  return { update, create, remove }
}