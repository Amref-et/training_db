import { Capacitor, CapacitorHttp } from '@capacitor/core';
import { Preferences } from '@capacitor/preferences';

const TOKEN_KEY = 'hil_mobile_token';
const API_BASE_URL_KEY = 'hil_mobile_api_base_url';
const CACHE_PREFIX = 'hil_mobile_cache:';
const QUEUE_KEY = 'hil_mobile_sync_queue';
const OFFLINE_STATUS_EVENT = 'hil-mobile-offline-status';

const defaultBaseUrl = import.meta.env.VITE_API_BASE_URL || 'https://et-dhis.amref.org/hil2';
const obsoleteDefaultBaseUrls = [
  'http://localhost:8000',
  'http://127.0.0.1:8000',
  'http://localhost/test/hil-v2',
  'http://127.0.0.1/test/hil-v2',
];

export const visibleTrainingEventStatuses = ['Ongoing', 'Up coming'];

export type ApiUser = {
  id: number;
  name: string;
  email: string;
  roles: string[];
  permissions: string[];
};

export type ApiOption = {
  value: number;
  label: string;
  hint?: string;
  mobile_phone?: string;
  region_id?: number | null;
  zone_id?: number | null;
  woreda_id?: number | null;
};

export type Region = {
  id: number;
  name: string;
};

export type Zone = {
  id: number;
  name: string;
  region_id: number | null;
};

export type Woreda = {
  id: number;
  name: string;
  region_id: number | null;
  zone_id: number | null;
};

export type Participant = {
  id: number;
  participant_code: string | null;
  name: string;
  first_name: string;
  father_name: string;
  grandfather_name: string;
  age: number | null;
  gender: string;
  mobile_phone: string;
  email: string | null;
  profession: string;
  region?: Region;
  zone?: Zone;
  woreda?: Woreda;
  organization?: { id: number; name: string };
};

export type TrainingEvent = {
  id: number;
  event_name: string;
  training_city: string | null;
  course_venue: string | null;
  start_date: string;
  end_date: string;
  status: string;
  workshop_count?: number | null;
  training?: { id: number; title: string } | null;
  project?: { id: number; project_code: string | null; project_name: string | null } | null;
  training_region?: Region | null;
  participants_count?: number | null;
};

export type WorkflowStep = {
  step: number;
  title: string;
  complete: boolean;
};

export type WorkflowEventSummary = {
  event: TrainingEvent;
  pending_join_requests_count: number;
  enrollments_count: number;
  workshop_count: number;
};

export type WorkflowJoinRequest = {
  id: number;
  status: 'pending' | 'approved' | 'rejected' | string;
  requested_message: string | null;
  reviewer_notes: string | null;
  requested_at: string | null;
  reviewed_at: string | null;
  participant: {
    id: number;
    name: string;
    mobile_phone: string | null;
    participant_code: string | null;
  } | null;
};

export type WorkflowScore = {
  workshop_number: number;
  pre_test_score: number | null;
  mid_test_score: number | null;
  post_test_score: number | null;
};

export type WorkflowEnrollment = {
  id: number;
  training_event_id: number;
  participant_id: number;
  final_score: number | null;
  participant: {
    id: number;
    name: string;
    mobile_phone: string | null;
    participant_code: string | null;
  } | null;
  scores: WorkflowScore[];
};

export type WorkflowWorkshop = {
  workshop_number: number;
  start_date: string | null;
  end_date: string | null;
  progress: {
    completed: number;
    total: number;
    is_complete: boolean;
  };
};

export type WorkflowSummary = {
  participants_count: number;
  with_final_scores: number;
  avg_final_score: number | null;
  avg_pre_score: number | null;
  avg_post_score: number | null;
  required_workshop_count: number;
};

export type WorkflowReportWorkshop = {
  workshop_number: number;
  start_date: string | null;
  end_date: string | null;
  avg_pre_score: number | null;
  avg_post_score: number | null;
};

export type WorkflowReportParticipant = {
  enrollment_id: number;
  participant: {
    id: number;
    name: string;
    mobile_phone: string | null;
    participant_code: string | null;
  } | null;
  avg_pre_score: number | null;
  avg_post_score: number | null;
  final_score: number | null;
};

export type WorkflowCloseoutPicture = {
  path: string;
  url: string | null;
};

export type WorkflowCloseout = {
  statuses: string[];
  report_path: string | null;
  report_url: string | null;
  pictures: WorkflowCloseoutPicture[];
};

export type WorkflowDetail = {
  event: TrainingEvent;
  steps: WorkflowStep[];
  join_requests: WorkflowJoinRequest[];
  enrollments: WorkflowEnrollment[];
  workshops: WorkflowWorkshop[];
  summary: WorkflowSummary;
  report_workshops: WorkflowReportWorkshop[];
  report_participants: WorkflowReportParticipant[];
  closeout: WorkflowCloseout;
};

export type JoinRequestResponse = {
  data: {
    status: 'pending' | 'already_enrolled' | 'already_pending' | string;
    event?: TrainingEvent;
    join_request?: {
      id: number;
      status: string;
      requested_message: string | null;
      requested_at: string | null;
    } | null;
  };
  message?: string;
};

export type EnrollmentResponse = {
  data: {
    status: 'enrolled' | 'already_enrolled' | string;
    enrollment?: {
      id: number;
      training_event_id: number;
      participant_id: number;
    };
    event?: TrainingEvent;
    participant?: {
      id: number;
      name: string;
      mobile_phone: string | null;
      participant_code: string | null;
    };
  };
  message?: string;
};

export type DashboardSummary = {
  summary?: Record<string, unknown>;
  filters?: Record<string, unknown>;
};

export type RegistrationOptions = {
  regions: Region[];
  zones: Zone[];
  woredas: Woreda[];
  professions: { name: string }[];
  selected_organization: ApiOption | null;
};

export type AppearanceSettings = {
  site: {
    name: string;
    tagline: string | null;
  };
  logos: {
    header_url: string | null;
    footer_url: string | null;
    favicon_url: string | null;
    header_height: number;
  };
  colors: {
    header_background: string;
    header_text: string;
    header_link: string;
    body_background: string;
    body_text: string;
    body_panel: string;
    body_accent: string;
    footer_background: string;
    footer_text: string;
    footer_link: string;
  };
  radii: {
    sm: number;
    md: number;
    lg: number;
    xl: number;
    pill: number;
  };
  login: {
    eyebrow: string | null;
    title: string | null;
    subtitle: string | null;
    background_start: string;
    background_end: string;
    background_accent: string;
    card_background: string;
    form_title: string | null;
    form_subtitle: string | null;
    email_label: string;
    password_label: string;
    submit_label: string;
    feature_1: string | null;
    feature_2: string | null;
    feature_3: string | null;
  };
};

type RequestOptions = {
  method?: string;
  body?: unknown;
  token?: string | null;
  baseUrl?: string | null;
};

type ApiTransportResponse = {
  ok: boolean;
  status: number;
  payload: unknown;
};

type QueuedTokenMode = 'stored' | 'none';

type QueuedRequest = {
  id: string;
  label: string;
  path: string;
  method: string;
  body: unknown;
  tokenMode: QueuedTokenMode;
  createdAt: string;
  attempts: number;
  lastError?: string;
};

type CachedValue<T> = {
  data: T;
  savedAt: string;
};

export type OfflineSnapshot = {
  online: boolean;
  pending: number;
  syncing: boolean;
  lastSyncAt: string | null;
  lastError: string | null;
};

export type QueuedMutationResponse = {
  data: {
    status: 'queued';
    queued: true;
    queue_id: string;
  };
  message: string;
};

export function isQueuedMutationResponse(response: unknown): response is QueuedMutationResponse {
  return isRecord(response) && isRecord(response.data) && response.data.queued === true;
}

let syncing = false;
let lastSyncAt: string | null = null;
let lastSyncError: string | null = null;

export async function getStoredToken(): Promise<string | null> {
  const { value } = await Preferences.get({ key: TOKEN_KEY });

  return value;
}

export async function setStoredToken(token: string | null): Promise<void> {
  if (token) {
    await Preferences.set({ key: TOKEN_KEY, value: token });

    return;
  }

  await Preferences.remove({ key: TOKEN_KEY });
}

export async function getApiBaseUrl(): Promise<string> {
  const { value } = await Preferences.get({ key: API_BASE_URL_KEY });

  const storedBaseUrl = value ? normalizeBaseUrl(value) : null;
  const storedValue = value ? value.trim().replace(/\/+$/, '') : null;

  if (storedBaseUrl && storedValue && storedBaseUrl !== storedValue) {
    await Preferences.set({ key: API_BASE_URL_KEY, value: storedBaseUrl });
  }

  if (storedBaseUrl && obsoleteDefaultBaseUrls.includes(storedBaseUrl)) {
    await Preferences.set({ key: API_BASE_URL_KEY, value: normalizeBaseUrl(defaultBaseUrl) });
    await setStoredToken(null);

    return normalizeBaseUrl(defaultBaseUrl);
  }

  return storedBaseUrl || normalizeBaseUrl(defaultBaseUrl);
}

export async function setApiBaseUrl(value: string): Promise<void> {
  const nextBaseUrl = normalizeBaseUrl(value);
  const currentBaseUrl = await getApiBaseUrl();

  await Preferences.set({ key: API_BASE_URL_KEY, value: nextBaseUrl });

  if (nextBaseUrl !== currentBaseUrl) {
    await setStoredToken(null);
  }
}

export function isProbablyOnline(): boolean {
  return typeof navigator === 'undefined' ? true : navigator.onLine;
}

export async function getOfflineSnapshot(): Promise<OfflineSnapshot> {
  const queue = await getQueue();

  return {
    online: isProbablyOnline(),
    pending: queue.length,
    syncing,
    lastSyncAt,
    lastError: lastSyncError,
  };
}

export async function notifyOfflineStatus(): Promise<void> {
  if (typeof window === 'undefined') {
    return;
  }

  window.dispatchEvent(new CustomEvent(OFFLINE_STATUS_EVENT, { detail: await getOfflineSnapshot() }));
}

export function offlineStatusEventName(): string {
  return OFFLINE_STATUS_EVENT;
}

export async function syncQueuedRequests(): Promise<{ synced: number; failed: number; pending: number }> {
  if (syncing || !isProbablyOnline()) {
    const queue = await getQueue();

    return { synced: 0, failed: 0, pending: queue.length };
  }

  syncing = true;
  lastSyncError = null;
  await notifyOfflineStatus();

  let synced = 0;
  let failed = 0;
  let queue = await getQueue();

  for (const queued of queue) {
    try {
      await request(queued.path, {
        method: queued.method,
        body: queued.body,
        token: queued.tokenMode === 'none' ? null : undefined,
      });
      queue = queue.filter((item) => item.id !== queued.id);
      synced += 1;
      await setQueue(queue);
      continue;
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Sync failed.';

      if (err instanceof ApiError && err.status >= 400 && err.status < 500 && err.status !== 429) {
        queue = queue.filter((item) => item.id !== queued.id);
        failed += 1;
        lastSyncError = message;
        await setQueue(queue);
        continue;
      }

      queue = queue.map((item) =>
        item.id === queued.id
          ? { ...item, attempts: item.attempts + 1, lastError: message }
          : item
      );
      failed += 1;
      lastSyncError = message;
      await setQueue(queue);
      break;
    }
  }

  if (synced > 0) {
    lastSyncAt = new Date().toISOString();
  }

  syncing = false;
  await notifyOfflineStatus();

  return { synced, failed, pending: queue.length };
}

export async function login(email: string, password: string, deviceName: string) {
  const response = await request<{
    data: {
      token_type: string;
      access_token: string;
      expires_at: string | null;
      abilities: string[];
      user: ApiUser;
    };
  }>('/api/mobile/login', {
    method: 'POST',
    body: { email, password, device_name: deviceName },
    token: null,
  });

  await setStoredToken(response.data.access_token);
  await writeCache(await cacheKey('/api/mobile/me'), {
    data: {
      user: response.data.user,
    },
  });

  return response.data;
}

export async function logout(): Promise<void> {
  const token = await getStoredToken();

  if (token) {
    try {
      await request('/api/mobile/logout', { method: 'POST', token });
    } catch {
      // Local logout should complete even when the remote token is already expired.
    }
  }

  await setStoredToken(null);
}

export async function me(token?: string | null) {
  const response = await requestWithCache<{ data: { user: ApiUser } }>('/api/mobile/me', {
    token: token ?? (await getStoredToken()),
  });

  return response.data.user;
}

export async function appearance(baseUrl?: string | null) {
  const response = await requestWithCache<{ data: AppearanceSettings }>('/api/mobile/appearance', {
    token: null,
    baseUrl,
  });

  return response.data;
}

export async function dashboard() {
  return requestWithCache<DashboardSummary>('/api/v1/dashboard');
}

export async function participants(query = '') {
  const params = new URLSearchParams({ per_page: '25' });

  if (query.trim() !== '') {
    params.set('q', query.trim());
  }

  const response = await requestWithCache<{ data: Participant[]; meta: Record<string, number> }>(
    `/api/v1/participants?${params.toString()}`
  );

  return response;
}

export async function trainingEvents(query = '', statuses: string[] = []) {
  const params = new URLSearchParams({ per_page: '25' });

  if (query.trim() !== '') {
    params.set('q', query.trim());
  }

  statuses.forEach((status) => {
    params.append('statuses[]', status);
  });

  const response = await requestWithCache<{ data: TrainingEvent[]; meta: Record<string, number> }>(
    `/api/v1/training-events?${params.toString()}`
  );

  return response;
}

export async function registrationOptions() {
  const response = await requestWithCache<{ data: RegistrationOptions }>('/api/mobile/participant-registration/options', {
    token: null,
  });

  return response.data;
}

export async function organizationOptions(filters: {
  q?: string;
  region_id?: string;
  zone_id?: string;
  woreda_id?: string;
}) {
  const params = new URLSearchParams();

  Object.entries(filters).forEach(([key, value]) => {
    if (value) {
      params.set(key, value);
    }
  });

  const response = await requestWithCache<{ data: { options: ApiOption[] } }>(
    `/api/mobile/participant-registration/organization-options?${params.toString()}`,
    { token: null }
  );

  return response.data.options;
}

export async function registerParticipant(payload: Record<string, unknown>) {
  return requestOrQueue<QueuedMutationResponse | { message?: string }>('/api/mobile/participant-registration', {
    method: 'POST',
    body: payload,
    token: null,
  }, 'Participant registration', 'none');
}

export async function joinRequestOptions() {
  const response = await requestWithCache<{ data: { events: TrainingEvent[] } }>(
    '/api/mobile/training-event-join-request/options',
    { token: null }
  );

  return response.data.events;
}

export async function participantOptions(query: string) {
  const params = new URLSearchParams({ q: query });
  const response = await requestWithCache<{ data: { options: ApiOption[] } }>(
    `/api/mobile/training-event-join-request/participant-options?${params.toString()}`,
    { token: null }
  );

  return response.data.options;
}

export async function submitJoinRequest(payload: Record<string, unknown>) {
  return requestOrQueue<JoinRequestResponse | QueuedMutationResponse>('/api/mobile/training-event-join-request', {
    method: 'POST',
    body: payload,
    token: null,
  }, 'Training event join request', 'none');
}

export async function enrollParticipant(trainingEventId: number, participantId: number) {
  return requestOrQueue<EnrollmentResponse | QueuedMutationResponse>(`/api/mobile/training-events/${trainingEventId}/enrollments`, {
    method: 'POST',
    body: { participant_id: participantId },
  }, 'Training event enrolment', 'stored');
}

export async function trainingWorkflowEvents(query = '') {
  const params = new URLSearchParams();

  if (query.trim() !== '') {
    params.set('q', query.trim());
  }

  const suffix = params.toString() ? `?${params.toString()}` : '';
  const response = await requestWithCache<{ data: { events: WorkflowEventSummary[] } }>(
    `/api/mobile/training-workflow/events${suffix}`
  );

  return response.data.events;
}

export async function trainingWorkflowEvent(trainingEventId: number) {
  const response = await requestWithCache<{ data: WorkflowDetail }>(
    `/api/mobile/training-workflow/events/${trainingEventId}`
  );

  return response.data;
}

export async function approveWorkflowJoinRequest(trainingEventId: number, joinRequestId: number, reviewerNotes = '') {
  const response = await requestOrQueue<{ data: WorkflowDetail; message?: string } | QueuedMutationResponse>(
    `/api/mobile/training-workflow/events/${trainingEventId}/join-requests/${joinRequestId}/approve`,
    {
      method: 'POST',
      body: { reviewer_notes: reviewerNotes },
    },
    'Join request approval',
    'stored'
  );

  return response;
}

export async function rejectWorkflowJoinRequest(trainingEventId: number, joinRequestId: number, reviewerNotes = '') {
  const response = await requestOrQueue<{ data: WorkflowDetail; message?: string } | QueuedMutationResponse>(
    `/api/mobile/training-workflow/events/${trainingEventId}/join-requests/${joinRequestId}/reject`,
    {
      method: 'POST',
      body: { reviewer_notes: reviewerNotes },
    },
    'Join request rejection',
    'stored'
  );

  return response;
}

export async function updateWorkflowWorkshopCount(trainingEventId: number, workshopCount: number) {
  const response = await requestOrQueue<{ data: WorkflowDetail; message?: string } | QueuedMutationResponse>(
    `/api/mobile/training-workflow/events/${trainingEventId}/workshop-count`,
    {
      method: 'POST',
      body: { workshop_count: workshopCount },
    },
    'Workshop structure',
    'stored'
  );

  return response;
}

export async function saveWorkflowScores(
  trainingEventId: number,
  payload: {
    workshop_number: number;
    workshop_start_date?: string | null;
    workshop_end_date?: string | null;
    scores: {
      enrollment_id: number;
      pre_test_score: number | null;
      mid_test_score: number | null;
      post_test_score: number | null;
    }[];
  }
) {
  const response = await requestOrQueue<{ data: WorkflowDetail; message?: string } | QueuedMutationResponse>(
    `/api/mobile/training-workflow/events/${trainingEventId}/workshops`,
    {
      method: 'POST',
      body: payload,
    },
    'Workshop scores',
    'stored'
  );

  return response;
}

export async function updateWorkflowCloseout(trainingEventId: number, payload: FormData | Record<string, unknown>) {
  if (!(payload instanceof FormData)) {
    const response = await requestOrQueue<{ data: WorkflowDetail; message?: string } | QueuedMutationResponse>(
      `/api/mobile/training-workflow/events/${trainingEventId}/closeout`,
      {
        method: 'POST',
        body: payload,
      },
      'Training event closeout',
      'stored'
    );

    return response;
  }

  const response = await request<{ data: WorkflowDetail; message?: string }>(
    `/api/mobile/training-workflow/events/${trainingEventId}/closeout`,
    {
      method: 'POST',
      body: payload,
    }
  );

  return response;
}

async function requestWithCache<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const key = await cacheKey(path, options.baseUrl);

  try {
    const response = await request<T>(path, options);
    await writeCache(key, response);

    return response;
  } catch (err) {
    if (isOfflineError(err)) {
      const cached = await readCache<T>(key);

      if (cached) {
        return cached.data;
      }

      throw new ApiError('Offline and no cached data is available.', 0, { offline: true });
    }

    throw err;
  }
}

async function requestOrQueue<T>(
  path: string,
  options: RequestOptions,
  label: string,
  tokenMode: QueuedTokenMode
): Promise<T> {
  try {
    return await request<T>(path, options);
  } catch (err) {
    if (!isOfflineError(err)) {
      throw err;
    }

    const queueId = await enqueueRequest({
      label,
      path,
      method: options.method || 'POST',
      body: options.body ?? null,
      tokenMode,
    });

    return queuedResponse(queueId, label) as T;
  }
}

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const baseUrl = options.baseUrl ? normalizeBaseUrl(options.baseUrl) : await getApiBaseUrl();
  const token = options.token === undefined ? await getStoredToken() : options.token;
  const method = options.method || 'GET';
  const url = `${baseUrl}${path}`;
  const headers: Record<string, string> = { Accept: 'application/json' };

  if (!(options.body instanceof FormData)) {
    headers['Content-Type'] = 'application/json';
  }

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  let response: ApiTransportResponse;

  try {
    response = Capacitor.isNativePlatform() && !(options.body instanceof FormData)
      ? await nativeHttpRequest(url, method, headers, options.body)
      : await browserFetchRequest(url, method, headers, options.body);
  } catch {
    throw new ApiError(networkUnavailableMessage(baseUrl), 0, {
      offline: true,
      base_url: baseUrl,
      url,
      native: Capacitor.isNativePlatform(),
    });
  }

  const payload = response.payload ?? {};

  if (!response.ok) {
    if (response.status === 401 && token) {
      await setStoredToken(null);

      if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('hil-mobile-unauthorized'));
      }
    }

    throw new ApiError(errorMessage(payload, response.status), response.status, payload);
  }

  return payload as T;
}

async function browserFetchRequest(
  url: string,
  method: string,
  headers: Record<string, string>,
  body: unknown
): Promise<ApiTransportResponse> {
  const response = await fetch(url, {
    method,
    headers,
    body: body instanceof FormData ? body : body === undefined ? undefined : JSON.stringify(body),
  });

  return {
    ok: response.ok,
    status: response.status,
    payload: await response.json().catch(() => ({})),
  };
}

async function nativeHttpRequest(
  url: string,
  method: string,
  headers: Record<string, string>,
  body: unknown
): Promise<ApiTransportResponse> {
  const response = await CapacitorHttp.request({
    url,
    method,
    headers,
    data: body instanceof FormData ? undefined : body,
    responseType: 'json',
    connectTimeout: 15000,
    readTimeout: 30000,
  });

  return {
    ok: response.status >= 200 && response.status < 300,
    status: response.status,
    payload: parseNativePayload(response.data),
  };
}

async function cacheKey(path: string, baseUrl?: string | null): Promise<string> {
  const base = baseUrl ? normalizeBaseUrl(baseUrl) : await getApiBaseUrl();

  return `${CACHE_PREFIX}${base}${path}`;
}

async function readCache<T>(key: string): Promise<CachedValue<T> | null> {
  const { value } = await Preferences.get({ key });

  if (!value) {
    return null;
  }

  try {
    return JSON.parse(value) as CachedValue<T>;
  } catch {
    await Preferences.remove({ key });

    return null;
  }
}

async function writeCache<T>(key: string, data: T): Promise<void> {
  await Preferences.set({
    key,
    value: JSON.stringify({
      data,
      savedAt: new Date().toISOString(),
    } satisfies CachedValue<T>),
  });
}

async function enqueueRequest(input: Omit<QueuedRequest, 'id' | 'createdAt' | 'attempts'>): Promise<string> {
  const queue = await getQueue();
  const id = `${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;

  await setQueue([
    ...queue,
    {
      ...input,
      id,
      createdAt: new Date().toISOString(),
      attempts: 0,
    },
  ]);
  await notifyOfflineStatus();

  return id;
}

async function getQueue(): Promise<QueuedRequest[]> {
  const { value } = await Preferences.get({ key: QUEUE_KEY });

  if (!value) {
    return [];
  }

  try {
    const parsed = JSON.parse(value);

    return Array.isArray(parsed) ? parsed as QueuedRequest[] : [];
  } catch {
    await Preferences.remove({ key: QUEUE_KEY });

    return [];
  }
}

async function setQueue(queue: QueuedRequest[]): Promise<void> {
  if (queue.length === 0) {
    await Preferences.remove({ key: QUEUE_KEY });
  } else {
    await Preferences.set({ key: QUEUE_KEY, value: JSON.stringify(queue) });
  }

  await notifyOfflineStatus();
}

function queuedResponse(queueId: string, label: string): QueuedMutationResponse {
  return {
    data: {
      status: 'queued',
      queued: true,
      queue_id: queueId,
    },
    message: `${label} saved offline. It will sync when the connection returns.`,
  };
}

function isOfflineError(err: unknown): boolean {
  return err instanceof ApiError && err.status === 0;
}

function normalizeBaseUrl(value: string): string {
  const trimmed = value.trim().replace(/\/+$/, '');

  if (!trimmed) {
    return trimmed;
  }

  try {
    const url = new URL(trimmed);
    const apiIndex = url.pathname.toLowerCase().indexOf('/api/mobile');

    if (apiIndex !== -1) {
      url.pathname = url.pathname.slice(0, apiIndex) || '/';
      url.search = '';
      url.hash = '';

      return url.toString().replace(/\/+$/, '');
    }

    if (url.pathname.toLowerCase().endsWith('/api')) {
      url.pathname = url.pathname.slice(0, -4) || '/';
      url.search = '';
      url.hash = '';

      return url.toString().replace(/\/+$/, '');
    }
  } catch {
    // Keep non-URL values usable in browser development while still removing API suffixes.
  }

  return trimmed
    .replace(/\/api\/mobile(?:\/.*)?$/i, '')
    .replace(/\/api$/i, '')
    .replace(/\/+$/, '');
}

function parseNativePayload(value: unknown): unknown {
  if (typeof value !== 'string') {
    return value ?? {};
  }

  if (value.trim() === '') {
    return {};
  }

  try {
    return JSON.parse(value);
  } catch {
    return { message: value };
  }
}

function networkUnavailableMessage(baseUrl: string): string {
  if (/^https?:\/\/(localhost|127\.0\.0\.1)(?::\d+)?(?:\/|$)/i.test(baseUrl)) {
    return 'The installed app cannot reach localhost on your computer. Open Connection and use your computer LAN IP, for example http://192.168.1.10/test/hil-v2.';
  }

  return `Cannot reach Laravel at ${baseUrl}. Check that the phone is on the same network, the URL is correct, and Windows or Laragon allows LAN access.`;
}

function errorMessage(payload: unknown, status: number): string {
  if (isRecord(payload)) {
    if (typeof payload.message === 'string') {
      return payload.message;
    }

    if (isRecord(payload.errors)) {
      const firstError = Object.values(payload.errors).flat()[0];

      if (typeof firstError === 'string') {
        return firstError;
      }
    }
  }

  return `Request failed with status ${status}`;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null;
}

export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public payload: unknown
  ) {
    super(message);
  }
}
