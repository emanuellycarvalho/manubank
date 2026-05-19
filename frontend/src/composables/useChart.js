import { nextTick, onMounted, onUnmounted, watch } from 'vue'
import { Chart } from '@/utils/chartSetup.js'

/**
 * Lifecycle helper for Chart.js inside Vue 3.
 * Always keeps <canvas> in the DOM; re-renders after nextTick when data arrives.
 *
 * @param {import('vue').Ref<HTMLCanvasElement|null>} canvasRef
 * @param {() => boolean} hasData  — function so reactivity is read at render time
 * @param {() => object}  getConfig — returns Chart.js config object
 */
export function useChart(canvasRef, hasData, getConfig) {
  let chart = null

  async function renderChart() {
    if (chart) {
      chart.destroy()
      chart = null
    }

    if (!hasData()) return

    await nextTick()

    if (!canvasRef.value) return

    try {
      chart = new Chart(canvasRef.value, getConfig())
    } catch (err) {
      console.error('[useChart] render error:', err)
    }
  }

  onMounted(renderChart)
  onUnmounted(() => {
    chart?.destroy()
    chart = null
  })

  return { renderChart }
}
