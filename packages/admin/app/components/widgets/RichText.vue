<script setup lang="ts">
import type { SchemaProperty } from '~/composables/useSchema'

const props = defineProps<{
  modelValue: string
  label?: string
  description?: string
  required?: boolean
  disabled?: boolean
  schema?: SchemaProperty
}>()

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const allowedTags = new Set([
  'P', 'BR', 'B', 'I', 'U', 'STRONG', 'EM', 'A',
  'UL', 'OL', 'LI', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6',
  'BLOCKQUOTE', 'PRE', 'CODE', 'SUB', 'SUP', 'HR',
])

function sanitizeHtml(html: string): string {
  if (!html) return ''
  const doc = new DOMParser().parseFromString(html, 'text/html')
  function walk(node: Node): string {
    if (node.nodeType === Node.TEXT_NODE) return node.textContent ?? ''
    if (node.nodeType !== Node.ELEMENT_NODE) return ''
    const el = node as Element
    if (!allowedTags.has(el.tagName)) {
      return Array.from(el.childNodes).map(walk).join('')
    }
    const tag = el.tagName.toLowerCase()
    const children = Array.from(el.childNodes).map(walk).join('')
    if (tag === 'a') {
      const href = el.getAttribute('href') ?? ''
      if (href.startsWith('http://') || href.startsWith('https://') || href.startsWith('/')) {
        return `<${tag} href="${href}">${children}</${tag}>`
      }
      return children
    }
    if (tag === 'br' || tag === 'hr') return `<${tag}>`
    return `<${tag}>${children}</${tag}>`
  }
  return Array.from(doc.body.childNodes).map(walk).join('')
}

const sanitized = computed(() => sanitizeHtml(props.modelValue))

function onInput(event: Event) {
  const el = event.target as HTMLDivElement
  emit('update:modelValue', el.innerHTML)
}
</script>

<template>
  <div class="field">
    <label v-if="label" class="field-label">
      {{ label }}
      <span v-if="required" class="required">*</span>
    </label>
    <div
      contenteditable
      class="field-input field-richtext"
      :class="{ disabled }"
      v-html="sanitized"
      @input="onInput"
    />
    <p v-if="description" class="field-description">{{ description }}</p>
  </div>
</template>
