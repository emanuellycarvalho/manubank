import {
  Chart,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Title,
  Tooltip,
  Legend,
  Filler,
} from 'chart.js'

Chart.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Title,
  Tooltip,
  Legend,
  Filler,
)

Chart.defaults.font.family = "'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif"
Chart.defaults.font.size   = 12
Chart.defaults.color       = '#e7e7e7'
Chart.defaults.borderColor = '#485696'

const scaleDefaults = {
  grid:  { color: 'rgba(72, 86, 150, 0.25)' },
  ticks: { color: '#485696' },
}

export function applyChartTheme() {
  Chart.defaults.color       = '#e7e7e7'
  Chart.defaults.borderColor = '#485696'
  Chart.defaults.scales.linear   = { ...Chart.defaults.scales.linear,   ...scaleDefaults }
  Chart.defaults.scales.category = { ...Chart.defaults.scales.category, ...scaleDefaults }
}

applyChartTheme()

export { Chart }
