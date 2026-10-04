export async function mapLimited<T, U>(items: readonly T[], concurrency: number, map: (item: T, index: number) => Promise<U>): Promise<U[]> {
  const results = new Array<U>(items.length)
  let nextIndex = 0

  async function worker() {
    while (true) {
      const index = nextIndex++
      if (index >= items.length) return
      results[index] = await map(items[index], index)
    }
  }

  await Promise.all(Array.from({ length: Math.min(Math.max(1, concurrency), items.length) }, () => worker()))
  return results
}
