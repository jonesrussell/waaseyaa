<script setup lang="ts">
import { useLanguage } from '~/composables/useLanguage'
import { useSchema } from '~/composables/useSchema'

const route = useRoute()
const { t } = useLanguage()

const entityType = computed(() => route.params.entityType as string)
const { schema, fetch: fetchSchema } = useSchema(entityType.value)
onMounted(() => fetchSchema())
const entityLabel = computed(() => schema.value?.title ?? entityType.value)
const config = useRuntimeConfig()
useHead({ title: computed(() => `${t('edit')} ${entityLabel.value} | ${config.public.appName}`) })
const entityId = computed(() => route.params.id as string)
const successMessage = ref('')
const errorMessage = ref('')

function onSaved() {
  successMessage.value = t('entity_saved')
  setTimeout(() => { successMessage.value = '' }, 3000)
}

function onError(message: string) {
  errorMessage.value = message
}
</script>

<template>
  <div>
    <div class="page-header">
      <h1>{{ t('edit') }} {{ entityLabel }} #{{ entityId }}</h1>
      <NuxtLink :to="`/${entityType}`" class="btn">
        {{ t('back_to_list') }}
      </NuxtLink>
    </div>

    <div v-if="successMessage" class="success">{{ successMessage }}</div>
    <div v-if="errorMessage" class="error">{{ errorMessage }}</div>

    <SchemaForm
      :entity-type="entityType"
      :entity-id="entityId"
      @saved="onSaved"
      @error="onError"
    />
  </div>
</template>
