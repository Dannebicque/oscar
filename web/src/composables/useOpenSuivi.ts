import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useMailsStore } from '../stores/mails'
import { useSuivisStore } from '../stores/suivis'

/** « Ouvrir » un suivi (F-59, F-70) : son message s'il y en a un, sinon sa fiche. Jamais une liste sans rapport. */
export function useOpenSuivi() {
  const router = useRouter()
  const mails = useMailsStore()
  const suivis = useSuivisStore()
  const detail = ref<string | null>(null)

  async function openSuivi(id: string) {
    const s = suivis.byId(id)
    if (!s) return
    if (s.mailId && !mails.find(s.mailId) && mails.items.length === 0) await mails.load()
    const m = mails.openTarget(s.mailId)
    if (m) await router.push({ name: 'mails', params: { id: m.id } })
    else detail.value = id
  }
  return { openSuivi, detail }
}
