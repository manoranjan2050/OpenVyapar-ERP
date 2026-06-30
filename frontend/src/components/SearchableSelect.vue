<template>
  <div class="relative" @keydown.escape="open = false">
    <input
      ref="inputRef"
      v-model="search"
      :placeholder="placeholder"
      class="input w-full"
      autocomplete="off"
      @focus="onFocus"
      @input="open = true"
      @blur="onBlur"
      @keydown.down.prevent="moveDown"
      @keydown.up.prevent="moveUp"
      @keydown.enter.prevent="selectHighlighted"
    />

    <!-- Dropdown teleported to body to escape overflow-x-auto clipping -->
    <Teleport to="body">
      <Transition name="dropdown">
        <div v-if="open && filtered.length > 0"
             class="fixed z-[9999] bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl max-h-52 overflow-y-auto"
             :style="dropdownStyle">
          <button v-for="(opt, i) in filtered" :key="opt.value" type="button"
                  class="w-full text-left px-3 py-2 text-sm transition-colors"
                  :class="i === highlighted ? 'bg-blue-50 dark:bg-blue-900/30' : 'hover:bg-gray-50 dark:hover:bg-gray-700/50'"
                  @mousedown.prevent="select(opt)">
            <slot name="option" :opt="opt">
              <div class="font-medium text-gray-900 dark:text-white">{{ opt.label }}</div>
              <div v-if="opt.sub" class="text-xs text-gray-400">{{ opt.sub }}</div>
            </slot>
          </button>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'

const props = withDefaults(defineProps<{
  modelValue: any
  options: { value: any; label: string; sub?: string }[]
  placeholder?: string
}>(), { placeholder: 'Search…' })

const emit = defineEmits<{
  (e: 'update:modelValue', v: any): void
  (e: 'change', v: any): void
}>()

const inputRef = ref<HTMLInputElement | null>(null)
const search = ref('')
const open = ref(false)
const highlighted = ref(0)
const dropdownStyle = ref({ top: '0px', left: '0px', width: '220px' })

watch(() => props.modelValue, (val) => {
  if (!val && val !== 0) { search.value = ''; return }
  const opt = props.options.find(o => o.value === val)
  if (opt) search.value = opt.label
}, { immediate: true })

watch(() => props.options, () => {
  if (props.modelValue) {
    const opt = props.options.find(o => o.value === props.modelValue)
    if (opt) search.value = opt.label
  }
})

const filtered = computed(() => {
  const q = search.value.toLowerCase().trim()
  if (!q) return props.options.slice(0, 40)
  return props.options.filter(o =>
    o.label.toLowerCase().includes(q) || (o.sub ?? '').toLowerCase().includes(q)
  ).slice(0, 25)
})

function updateDropdownPosition() {
  const el = inputRef.value
  if (!el) return
  const rect = el.getBoundingClientRect()
  dropdownStyle.value = {
    top: `${rect.bottom + 4}px`,
    left: `${rect.left}px`,
    width: `${Math.max(rect.width, 220)}px`,
  }
}

function onFocus() {
  updateDropdownPosition()
  open.value = true
  highlighted.value = 0
}

function onBlur() {
  setTimeout(() => { open.value = false }, 200)
}

function select(opt: { value: any; label: string }) {
  search.value = opt.label
  emit('update:modelValue', opt.value)
  emit('change', opt.value)
  open.value = false
}

function moveDown() {
  if (!open.value) { updateDropdownPosition(); open.value = true; return }
  highlighted.value = Math.min(highlighted.value + 1, filtered.value.length - 1)
}

function moveUp() {
  highlighted.value = Math.max(highlighted.value - 1, 0)
}

function selectHighlighted() {
  const opt = filtered.value[highlighted.value]
  if (opt) select(opt)
}
</script>

<style scoped>
.dropdown-enter-active, .dropdown-leave-active { transition: all 0.15s ease; }
.dropdown-enter-from, .dropdown-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
