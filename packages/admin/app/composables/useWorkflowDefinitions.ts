export interface DryRunRequest {
  workflow_id: string
  bundle: string
  from_state: string
  to_state: string
  account_uid: number
}

export interface DryRunResult {
  allowed: boolean
  neutral: boolean
  forbidden: boolean
  reason: string | null
  required_permission: string
  transition_id: string
  transition_label: string
}

export interface WorkflowState {
  id: string
  label: string
  weight: number
  metadata: Record<string, unknown>
}

export interface WorkflowTransition {
  id: string
  label: string
  from: string[]
  to: string
  weight: number
}

export interface WorkflowDefinition {
  id: string
  label: string
  states: WorkflowState[]
  transitions: WorkflowTransition[]
}

export function useWorkflowDefinitions() {
  const { apiFetch } = useApi()
  const workflows = ref<WorkflowDefinition[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchWorkflows() {
    loading.value = true
    error.value = null
    try {
      const response = await apiFetch<{ data: WorkflowDefinition[] }>('/api/workflow-definitions')
      workflows.value = response.data ?? []
    } catch (e: unknown) {
      const err = e as { data?: { errors?: Array<{ detail?: string }> }, message?: string }
      error.value = err?.data?.errors?.[0]?.detail ?? err?.message ?? 'Failed to load workflow definitions.'
    } finally {
      loading.value = false
    }
  }

  function findById(id: string): WorkflowDefinition | null {
    return workflows.value.find(w => w.id === id) ?? null
  }

  async function dryRun(payload: DryRunRequest): Promise<DryRunResult> {
    const response = await apiFetch<{ data: DryRunResult }>('/api/workflow-definitions/dry-run', {
      method: 'POST',
      body: payload,
    })
    return response.data
  }

  return { workflows, loading, error, fetchWorkflows, findById, dryRun }
}
