// @juego/shared — lo específico del juego compartido entre admin y app.
// Aquí viven los componentes visuales de las cartas/fichas (fuente única del
// render a PNG) y sus tipos. Añade tus componentes en ./components y
// expórtalos aquí (patrón: el playground del monorepo del motor).

export { default as CardRender } from './components/CardRender.vue'
export { default as HeroRender } from './components/HeroRender.vue'
export { default as CounterRender } from './components/CounterRender.vue'
export * from './types'
