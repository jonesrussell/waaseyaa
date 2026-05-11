<script setup lang="ts">
import { useWorkflowDefinitions, type DryRunResult, type WorkflowState } from '~/composables/useWorkflowDefinitions'
import { useLanguage } from '~/composables/useLanguage'

const props = defineProps<{
  workflowId: string
  states: WorkflowState[]
}>()

const { t } = useLanguage()
const { dryRun } = useWorkflowDefinitions()

const bundle = ref('')
const fromState = ref('')
const toState = ref('')
const accountUid = ref<number | null>(null)

const submitting = ref(false)
const submitError = ref<string | null>(null)
const result = ref<DryRunResult | null>(null)

async function submit() {
  if (!bundle.value || !fromState.value || !toState.value || accountUid.value === null) return

  submitting.value = true
  submitError.value = null
  result.value = null

  try {
    result.value = await dryRun({
      workflow_id: props.workflowId,
      bundle: bundle.value,
      from_state: fromState.value,
      to_state: toState.value,
      account_uid: accountUid.value,
    })
  } catch (e: unknown) {
    const err = e as { data?: { errors?: Array<{ detail?: string }> }; message?: string }
    submitError.value = err?.data?.errors?.[0]?.detail ?? err?.message ?? t('dry_run_error_generic')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <section class="dry-run-section" data-testid="workflow-dry-run">
    <h2 class="dry-run-title">{{ t('dry_run_title') }}</h2>
    <p class="dry-run-description">{{ t('dry_run_description') }}</p>

    <form class="dry-run-form" @submit.prevent="submit">
      <div class="form-row">
        <label class="form-label" for="dry-run-bundle">{{ t('dry_run_bundle') }}</label>
        <input
          id="dry-run-bundle"
          v-model="bundle"
          type="text"
          class="form-input"
          :placeholder="t('dry_run_bundle_placeholder')"
          required
        />
      </div>

      <div class="form-row">
        <label class="form-label" for="dry-run-from">{{ t('dry_run_from_state') }}</label>
        <select id="dry-run-from" v-model="fromState" class="form-select" required>
          <option value="" disabled>{{ t('dry_run_select_state') }}</option>
          <option v-for="state in states" :key="state.id" :value="state.id">
            {{ state.label }} ({{ state.id }})
          </option>
        </select>
      </div>

      <div class="form-row">
        <label class="form-label" for="dry-run-to">{{ t('dry_run_to_state') }}</label>
        <select id="dry-run-to" v-model="toState" class="form-select" required>
          <option value="" disabled>{{ t('dry_run_select_state') }}</option>
          <option v-for="state in states" :key="state.id" :value="state.id">
            {{ state.label }} ({{ state.id }})
          </option>
        </select>
      </div>

      <div class="form-row">
        <label class="form-label" for="dry-run-uid">{{ t('dry_run_account_uid') }}</label>
        <input
          id="dry-run-uid"
          v-model.number="accountUid"
          type="number"
          class="form-input"
          min="0"
          :placeholder="t('dry_run_account_uid_placeholder')"
          required
        />
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary" :disabled="submitting">
          {{ submitting ? t('dry_run_submitting') : t('dry_run_submit') }}
        </button>
      </div>
    </form>

    <div v-if="submitError" class="dry-run-result dry-run-result--error" data-testid="dry-run-error">
      {{ submitError }}
    </div>

    <div
      v-if="result"
      class="dry-run-result"
      :class="{
        'dry-run-result--allowed': result.allowed,
        'dry-run-result--forbidden': result.forbidden,
        'dry-run-result--neutral': result.neutral,
      }"
      data-testid="dry-run-result"
    >
      <div class="dry-run-verdict">
        <span v-if="result.allowed" class="verdict-icon" aria-hidden="true">✓</span>
        <span v-else-if="result.forbidden" class="verdict-icon" aria-hidden="true">✗</span>
        <span v-else class="verdict-icon" aria-hidden="true">◯</span>

        <strong v-if="result.allowed">{{ t('dry_run_result_allowed') }}</strong>
        <strong v-else-if="result.forbidden">{{ t('dry_run_result_forbidden') }}</strong>
        <strong v-else>{{ t('dry_run_result_neutral') }}</strong>
      </div>

      <p v-if="result.reason" class="dry-run-reason">{{ result.reason }}</p>

      <dl v-if="result.transition_id" class="dry-run-meta">
        <dt>{{ t('dry_run_transition_id') }}</dt>
        <dd><code>{{ result.transition_id }}</code> — {{ result.transition_label }}</dd>
        <dt>{{ t('dry_run_required_permission') }}</dt>
        <dd><code>{{ result.required_permission || '—' }}</code></dd>
      </dl>
    </div>
  </section>
</template>

<style scoped>
.dry-run-section {
  margin-bottom: 32px;
  padding-top: 24px;
  border-top: 1px solid var(--color-border);
}

.dry-run-title {
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 8px;
  color: var(--color-muted);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.dry-run-description {
  font-size: 13px;
  color: var(--color-muted);
  margin-bottom: 16px;
}

.dry-run-form {
  display: grid;
  gap: 12px;
  max-width: 480px;
  margin-bottom: 20px;
}

.form-row {
  display: grid;
  gap: 4px;
}

.form-label {
  font-size: 13px;
  font-weight: 500;
}

.form-input,
.form-select {
  padding: 6px 10px;
  border: 1px solid var(--color-border);
  border-radius: 4px;
  font-size: 14px;
  background: var(--color-surface);
  color: var(--color-text);
}

.form-input:focus,
.form-select:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: 1px;
}

.form-actions {
  padding-top: 4px;
}

.dry-run-result {
  margin-top: 16px;
  padding: 14px 16px;
  border-radius: 6px;
  border: 1px solid var(--color-border);
  max-width: 480px;
}

.dry-run-result--allowed {
  border-color: var(--color-primary);
  background: rgba(20, 184, 166, 0.06);
}

.dry-run-result--forbidden {
  border-color: #dc2626;
  background: rgba(220, 38, 38, 0.06);
}

.dry-run-result--neutral {
  border-color: var(--color-border);
  background: var(--color-bg);
}

.dry-run-result--error {
  border-color: #dc2626;
  background: rgba(220, 38, 38, 0.06);
  color: #dc2626;
}

.dry-run-verdict {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.verdict-icon {
  font-size: 16px;
  line-height: 1;
}

.dry-run-result--allowed .verdict-icon,
.dry-run-result--allowed strong {
  color: var(--color-primary);
}

.dry-run-result--forbidden .verdict-icon,
.dry-run-result--forbidden strong {
  color: #dc2626;
}

.dry-run-result--neutral .verdict-icon,
.dry-run-result--neutral strong {
  color: var(--color-muted);
}

.dry-run-reason {
  font-size: 13px;
  margin: 0 0 10px;
  color: var(--color-muted);
}

.dry-run-meta {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 4px 12px;
  font-size: 12px;
  margin: 0;
}

.dry-run-meta dt {
  color: var(--color-muted);
  font-weight: 500;
}

.dry-run-meta dd {
  margin: 0;
}
</style>
