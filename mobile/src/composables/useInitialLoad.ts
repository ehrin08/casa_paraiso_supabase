import { ref } from 'vue'

export function useInitialLoad() {
  const initialLoading = ref(true)

  async function loadInitial(task: () => Promise<unknown>): Promise<void> {
    try {
      await task()
    } finally {
      initialLoading.value = false
    }
  }

  return { initialLoading, loadInitial }
}
