<script setup lang="ts">
import { useLanguage } from '~/composables/useLanguage'
import { useSchema } from '~/composables/useSchema'

const route = useRoute()
const router = useRouter()
const { t } = useLanguage()

const entityType = computed(() => route.params.entityType as string)
const { schema, fetch: fetchSchema } = useSchema(entityType.value)
onMounted(() => fetchSchema())
const entityLabel = computed(() => schema.value?.title ?? entityType.value)
const config = useRuntimeConfig()
useHead({ title: computed(() => `${t('create')} ${entityLabel.value} | ${config.public.appName}`) })
const successMessage = ref('')
const errorMessage = ref('')

function onSaved(resource: any) {
  successMessage.value = t('entity_created')
  setTimeout(() => {
    router.push(`/${entityType.value}/${resource.id}`)
  }, 500)
}

function onError(message: string) {
  errorMessage.value = message
}
</script>

<template>
  <div>
    <div class="page-header">
      <h1>{{ t('create') }} {{ entityLabel }}</h1>
      <NuxtLink :to="`/${entityType}`" class="btn">
        {{ t('back_to_list') }}
      </NuxtLink>
    </div>

    <div v-if="successMessage" class="success">{{ successMessage }}</div>
    <div v-if="errorMessage" class="error">{{ errorMessage }}</div>

    <SchemaForm
      :entity-type="entityType"
      @saved="onSaved"
      @error="onError"
    />
  </div>
</template>
