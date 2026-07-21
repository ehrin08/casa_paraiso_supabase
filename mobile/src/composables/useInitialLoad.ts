import { ref } from 'vue'

export function useInitialLoad(hasData: () => boolean = () => false) {
  const initialLoading = ref(!hasData())

  async function loadInitial(task: () => Promise<unknown>): Promise<void> {
    initialLoading.value = !hasData()
    try {
      await task()
    } finally {
      initialLoading.value = false
    }
  }

  return { initialLoading, loadInitial }
}
