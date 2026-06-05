import {
  IonButton,
  IonCard,
  IonCardContent,
  IonCardHeader,
  IonCardTitle,
  IonCheckbox,
  IonInput,
  IonItem,
  IonLabel,
  IonSearchbar,
  IonSegment,
  IonSegmentButton,
  IonSelect,
  IonSelectOption,
  IonText,
} from '@ionic/react';
import { ChangeEvent, useEffect, useMemo, useState } from 'react';

import EmptyState from '../components/EmptyState';
import FieldError from '../components/FieldError';
import PageHeader from '../components/PageHeader';
import {
  approveWorkflowJoinRequest,
  rejectWorkflowJoinRequest,
  saveWorkflowScores,
  trainingWorkflowEvent,
  trainingWorkflowEvents,
  updateWorkflowCloseout,
  updateWorkflowWorkshopCount,
  WorkflowCloseout,
  WorkflowDetail,
  WorkflowEnrollment,
  WorkflowEventSummary,
  WorkflowReportWorkshop,
} from '../services/api';

type WorkflowView = 'event' | 'enrollment' | 'workshops' | 'report' | 'closeout';

type ScoreDraft = {
  pre_test_score: string;
  mid_test_score: string;
  post_test_score: string;
};

const fallbackStatuses = ['Pending', 'Up coming', 'Ongoing', 'Completed', 'Cancelled'];

export default function WorkflowPage() {
  const [events, setEvents] = useState<WorkflowEventSummary[]>([]);
  const [query, setQuery] = useState('');
  const [detail, setDetail] = useState<WorkflowDetail | null>(null);
  const [activeView, setActiveView] = useState<WorkflowView>('event');
  const [selectedWorkshop, setSelectedWorkshop] = useState(1);
  const [workshopCount, setWorkshopCount] = useState('1');
  const [workshopStartDate, setWorkshopStartDate] = useState('');
  const [workshopEndDate, setWorkshopEndDate] = useState('');
  const [scoreDrafts, setScoreDrafts] = useState<Record<number, ScoreDraft>>({});
  const [closeoutStatus, setCloseoutStatus] = useState('');
  const [removeReport, setRemoveReport] = useState(false);
  const [removePictures, setRemovePictures] = useState<Set<string>>(new Set());
  const [reportFile, setReportFile] = useState<File | null>(null);
  const [pictureFiles, setPictureFiles] = useState<File[]>([]);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const activeWorkshop = useMemo(
    () => detail?.workshops.find((workshop) => workshop.workshop_number === selectedWorkshop) || null,
    [detail, selectedWorkshop]
  );

  const loadEvents = async (search = query) => {
    setError(null);

    try {
      setEvents(await trainingWorkflowEvents(search));
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Training workflow unavailable.');
    }
  };

  const openEvent = async (eventId: number) => {
    setLoading(true);
    setError(null);
    setNotice(null);

    try {
      const workflow = await trainingWorkflowEvent(eventId);
      setDetail(workflow);
      setWorkshopCount(String(workflow.event.workshop_count || 1));
      setSelectedWorkshop(1);
      setActiveView('event');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to load workflow.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void loadEvents('');
  }, []);

  useEffect(() => {
    if (!detail) {
      setScoreDrafts({});

      return;
    }

    const nextDrafts: Record<number, ScoreDraft> = {};

    detail.enrollments.forEach((enrollment) => {
      const score = enrollment.scores.find((item) => item.workshop_number === selectedWorkshop);

      nextDrafts[enrollment.id] = {
        pre_test_score: valueToInput(score?.pre_test_score),
        mid_test_score: valueToInput(score?.mid_test_score),
        post_test_score: valueToInput(score?.post_test_score),
      };
    });

    setScoreDrafts(nextDrafts);
  }, [detail, selectedWorkshop]);

  useEffect(() => {
    setWorkshopStartDate(activeWorkshop?.start_date || '');
    setWorkshopEndDate(activeWorkshop?.end_date || '');
  }, [activeWorkshop]);

  useEffect(() => {
    if (!detail) {
      return;
    }

    const closeout = closeoutFor(detail);
    setCloseoutStatus(closeout.statuses.includes(detail.event.status) ? detail.event.status : closeout.statuses[0] || detail.event.status);
    setRemoveReport(false);
    setRemovePictures(new Set());
    setReportFile(null);
    setPictureFiles([]);
  }, [detail?.event.id, detail?.event.status, detail?.closeout?.report_path, detail?.closeout?.pictures.length]);

  const refreshDetail = (workflow: WorkflowDetail, message?: string) => {
    setDetail(workflow);
    setWorkshopCount(String(workflow.event.workshop_count || 1));
    setNotice(message || null);
    setError(null);
  };

  const selectStep = (step: number) => {
    setActiveView(viewForStep(step));
    setNotice(null);
    setError(null);
  };

  const approveRequest = async (joinRequestId: number) => {
    if (!detail) {
      return;
    }

    try {
      const response = await approveWorkflowJoinRequest(detail.event.id, joinRequestId);
      refreshDetail(response.data, response.message || 'Join request approved.');
      setActiveView('enrollment');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to approve join request.');
    }
  };

  const rejectRequest = async (joinRequestId: number) => {
    if (!detail) {
      return;
    }

    try {
      const response = await rejectWorkflowJoinRequest(detail.event.id, joinRequestId);
      refreshDetail(response.data, response.message || 'Join request rejected.');
      setActiveView('enrollment');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to reject join request.');
    }
  };

  const saveWorkshopCount = async () => {
    if (!detail) {
      return;
    }

    const count = Number(workshopCount);

    if (!Number.isInteger(count) || count < 1 || count > 20) {
      setError('Workshop count must be between 1 and 20.');

      return;
    }

    try {
      const response = await updateWorkflowWorkshopCount(detail.event.id, count);
      refreshDetail(response.data, response.message || 'Workshop structure updated.');
      setSelectedWorkshop(Math.min(selectedWorkshop, count));
      setActiveView('workshops');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to update workshop count.');
    }
  };

  const saveScores = async () => {
    if (!detail) {
      return;
    }

    try {
      const response = await saveWorkflowScores(detail.event.id, {
        workshop_number: selectedWorkshop,
        workshop_start_date: workshopStartDate || null,
        workshop_end_date: workshopEndDate || null,
        scores: detail.enrollments.map((enrollment) => {
          const draft = scoreDrafts[enrollment.id] || emptyDraft();

          return {
            enrollment_id: enrollment.id,
            pre_test_score: numericOrNull(draft.pre_test_score),
            mid_test_score: numericOrNull(draft.mid_test_score),
            post_test_score: numericOrNull(draft.post_test_score),
          };
        }),
      });

      refreshDetail(response.data, response.message || 'Workshop scores saved.');
      setActiveView('workshops');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to save scores.');
    }
  };

  const saveCloseout = async () => {
    if (!detail) {
      return;
    }

    if (!closeoutStatus) {
      setError('Select a closeout status.');

      return;
    }

    try {
      const hasFiles = Boolean(reportFile || pictureFiles.length > 0);
      const payload = hasFiles ? new FormData() : {
        status: closeoutStatus,
        remove_report: removeReport,
        remove_existing_pictures: Array.from(removePictures),
      };

      if (payload instanceof FormData) {
        payload.append('status', closeoutStatus);

        if (removeReport) {
          payload.append('remove_report', '1');
        }

        removePictures.forEach((path) => payload.append('remove_existing_pictures[]', path));

        if (reportFile) {
          payload.append('training_event_report', reportFile);
        }

        pictureFiles.forEach((file) => payload.append('training_event_pictures[]', file));
      }

      const response = await updateWorkflowCloseout(detail.event.id, payload);
      refreshDetail(response.data, response.message || 'Training event closeout updated.');
      setActiveView('closeout');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to save closeout.');
    }
  };

  const updateDraft = (enrollmentId: number, key: keyof ScoreDraft, value: string) => {
    setScoreDrafts((current) => ({
      ...current,
      [enrollmentId]: {
        ...(current[enrollmentId] || emptyDraft()),
        [key]: value,
      },
    }));
  };

  const togglePictureRemoval = (path: string, checked: boolean) => {
    setRemovePictures((current) => {
      const next = new Set(current);

      if (checked) {
        next.add(path);
      } else {
        next.delete(path);
      }

      return next;
    });
  };

  if (detail) {
    const closeout = closeoutFor(detail);
    const reportWorkshops = reportWorkshopsFor(detail);

    return (
      <PageHeader title="Workflow">
        <div className="workflow-detail-header">
          <IonButton fill="clear" size="small" onClick={() => setDetail(null)}>
            Back
          </IonButton>
          <IonText>
            <span>{detail.event.status}</span>
            <h1>{detail.event.event_name}</h1>
            <p>{[detail.event.training?.title, detail.event.training_city].filter(Boolean).join(' - ')}</p>
          </IonText>
        </div>

        <div className="workflow-step-grid">
          {detail.steps.map((step) => (
            <button
              type="button"
              className={`workflow-step ${step.complete ? 'complete' : ''} ${activeView === viewForStep(step.step) ? 'active' : ''}`}
              key={step.step}
              onClick={() => selectStep(step.step)}
            >
              <span>{step.step}</span>
              <strong>{step.title}</strong>
            </button>
          ))}
        </div>

        <IonSegment
          scrollable
          className="workflow-segment"
          value={activeView}
          onIonChange={(event) => setActiveView(String(event.detail.value || 'event') as WorkflowView)}
        >
          <IonSegmentButton value="event">Event</IonSegmentButton>
          <IonSegmentButton value="enrollment">Enroll</IonSegmentButton>
          <IonSegmentButton value="workshops">Workshop</IonSegmentButton>
          <IonSegmentButton value="report">Report</IonSegmentButton>
          <IonSegmentButton value="closeout">Closeout</IonSegmentButton>
        </IonSegment>

        {notice ? <IonText color="success">{notice}</IonText> : null}
        <FieldError message={error} />

        {activeView === 'event' ? (
          <section className="workflow-panel">
            <div className="metric-grid">
              <SummaryCard label="Participants" value={detail.summary.participants_count} />
              <SummaryCard label="Workshops" value={detail.summary.required_workshop_count} />
              <SummaryCard label="Pending Requests" value={detail.join_requests.filter((request) => request.status === 'pending').length} />
              <SummaryCard label="Final Scores" value={`${detail.summary.with_final_scores}/${detail.summary.participants_count}`} />
            </div>
            <IonCard>
              <IonCardHeader>
                <IonCardTitle>Event Details</IonCardTitle>
              </IonCardHeader>
              <IonCardContent>
                <div className="record-meta">
                  <span>{detail.event.start_date}</span>
                  <span>{detail.event.end_date}</span>
                  <span>{detail.event.course_venue || 'No venue'}</span>
                  <span>{detail.event.training_region?.name || 'No region'}</span>
                </div>
              </IonCardContent>
            </IonCard>
          </section>
        ) : null}

        {activeView === 'enrollment' ? (
          <section className="workflow-panel">
            <div>
              <h2 className="workflow-section-title">Join Requests</h2>
              {detail.join_requests.length === 0 ? <EmptyState title="No join requests" /> : null}
              <div className="card-list">
                {detail.join_requests.map((request) => (
                  <IonCard key={request.id}>
                    <IonCardHeader>
                      <IonCardTitle>{request.participant?.name || 'Participant request'}</IonCardTitle>
                      <IonText color="medium">
                        <p>{[request.status, request.participant?.mobile_phone].filter(Boolean).join(' - ')}</p>
                      </IonText>
                    </IonCardHeader>
                    <IonCardContent>
                      {request.requested_message ? <p>{request.requested_message}</p> : null}
                      {request.status === 'pending' ? (
                        <div className="button-row">
                          <IonButton fill="outline" onClick={() => void rejectRequest(request.id)}>
                            Reject
                          </IonButton>
                          <IonButton onClick={() => void approveRequest(request.id)}>Approve</IonButton>
                        </div>
                      ) : null}
                    </IonCardContent>
                  </IonCard>
                ))}
              </div>
            </div>

            <div>
              <h2 className="workflow-section-title">Enrolled Participants</h2>
              {detail.enrollments.length === 0 ? <EmptyState title="No enrolled participants" /> : null}
              <div className="card-list">
                {detail.enrollments.map((enrollment) => (
                  <IonCard key={enrollment.id}>
                    <IonCardHeader>
                      <IonCardTitle>{participantName(enrollment)}</IonCardTitle>
                      <IonText color="medium">
                        <p>{[enrollment.participant?.participant_code, enrollment.participant?.mobile_phone].filter(Boolean).join(' - ')}</p>
                      </IonText>
                    </IonCardHeader>
                    <IonCardContent>
                      <div className="record-meta">
                        <span>Final: {formatScore(enrollment.final_score)}</span>
                        <span>{completedScores(enrollment)} complete</span>
                      </div>
                    </IonCardContent>
                  </IonCard>
                ))}
              </div>
            </div>
          </section>
        ) : null}

        {activeView === 'workshops' ? (
          <section className="workflow-panel">
            <div className="workflow-score-toolbar">
              <IonItem lines="none" className="form-field">
                <IonLabel position="stacked">Number of Workshops</IonLabel>
                <IonInput
                  inputmode="numeric"
                  type="number"
                  min={1}
                  max={20}
                  value={workshopCount}
                  onIonInput={(event) => setWorkshopCount(String(event.detail.value || ''))}
                />
              </IonItem>
              <IonButton onClick={() => void saveWorkshopCount()}>Set</IonButton>
            </div>

            <IonItem lines="none" className="form-field">
              <IonLabel position="stacked">Selected Workshop</IonLabel>
              <IonSelect value={selectedWorkshop} onIonChange={(event) => setSelectedWorkshop(Number(event.detail.value || 1))}>
                {detail.workshops.map((workshop) => (
                  <IonSelectOption key={workshop.workshop_number} value={workshop.workshop_number}>
                    Workshop {workshop.workshop_number}
                  </IonSelectOption>
                ))}
              </IonSelect>
            </IonItem>

            <div className="two-column-form">
              <IonItem lines="none" className="form-field">
                <IonLabel position="stacked">Workshop Start Date</IonLabel>
                <IonInput type="date" value={workshopStartDate} onIonInput={(event) => setWorkshopStartDate(String(event.detail.value || ''))} />
              </IonItem>
              <IonItem lines="none" className="form-field">
                <IonLabel position="stacked">Workshop End Date</IonLabel>
                <IonInput type="date" value={workshopEndDate} onIonInput={(event) => setWorkshopEndDate(String(event.detail.value || ''))} />
              </IonItem>
            </div>

            {activeWorkshop ? (
              <div className="workflow-progress-line">
                Workshop {activeWorkshop.workshop_number}: {activeWorkshop.progress.completed}/{activeWorkshop.progress.total} participants completed
              </div>
            ) : null}

            {detail.enrollments.length === 0 ? <EmptyState title="Enroll participants before scoring" /> : null}
            <div className="card-list">
              {detail.enrollments.map((enrollment) => {
                const draft = scoreDrafts[enrollment.id] || emptyDraft();

                return (
                  <IonCard key={enrollment.id} className="score-card">
                    <IonCardHeader>
                      <IonCardTitle>{participantName(enrollment)}</IonCardTitle>
                    </IonCardHeader>
                    <IonCardContent>
                      <div className="score-grid">
                        <IonItem lines="none" className="form-field">
                          <IonLabel position="stacked">Pre</IonLabel>
                          <IonInput
                            inputmode="decimal"
                            type="number"
                            min={0}
                            max={100}
                            value={draft.pre_test_score}
                            onIonInput={(event) => updateDraft(enrollment.id, 'pre_test_score', String(event.detail.value || ''))}
                          />
                        </IonItem>
                        <IonItem lines="none" className="form-field">
                          <IonLabel position="stacked">Mid</IonLabel>
                          <IonInput
                            inputmode="decimal"
                            type="number"
                            min={0}
                            max={100}
                            value={draft.mid_test_score}
                            onIonInput={(event) => updateDraft(enrollment.id, 'mid_test_score', String(event.detail.value || ''))}
                          />
                        </IonItem>
                        <IonItem lines="none" className="form-field">
                          <IonLabel position="stacked">Post</IonLabel>
                          <IonInput
                            inputmode="decimal"
                            type="number"
                            min={0}
                            max={100}
                            value={draft.post_test_score}
                            onIonInput={(event) => updateDraft(enrollment.id, 'post_test_score', String(event.detail.value || ''))}
                          />
                        </IonItem>
                      </div>
                    </IonCardContent>
                  </IonCard>
                );
              })}
            </div>
            <IonButton expand="block" disabled={detail.enrollments.length === 0} onClick={() => void saveScores()}>
              Save Workshop {selectedWorkshop}
            </IonButton>
          </section>
        ) : null}

        {activeView === 'report' ? (
          <section className="workflow-panel">
            <div className="metric-grid">
              <SummaryCard label="Participants" value={detail.summary.participants_count} />
              <SummaryCard label="Final Scores" value={`${detail.summary.with_final_scores}/${detail.summary.participants_count}`} />
              <SummaryCard label="Avg Pre" value={formatScore(detail.summary.avg_pre_score)} />
              <SummaryCard label="Avg Post" value={formatScore(detail.summary.avg_post_score)} />
            </div>
            <div>
              <h2 className="workflow-section-title">Workshop Averages</h2>
              <div className="card-list">
                {reportWorkshops.map((workshop) => (
                  <IonCard key={workshop.workshop_number}>
                    <IonCardHeader>
                      <IonCardTitle>Workshop {workshop.workshop_number}</IonCardTitle>
                    </IonCardHeader>
                    <IonCardContent>
                      <div className="record-meta">
                        <span>{workshop.start_date || 'No start date'}</span>
                        <span>{workshop.end_date || 'No end date'}</span>
                        <span>Pre: {formatScore(workshop.avg_pre_score)}</span>
                        <span>Post: {formatScore(workshop.avg_post_score)}</span>
                      </div>
                    </IonCardContent>
                  </IonCard>
                ))}
              </div>
            </div>
            <div>
              <h2 className="workflow-section-title">Participant Scores</h2>
              {detail.report_participants?.length === 0 ? <EmptyState title="No participant score data" /> : null}
              <div className="card-list">
                {(detail.report_participants || []).map((row) => (
                  <IonCard key={row.enrollment_id}>
                    <IonCardHeader>
                      <IonCardTitle>{row.participant?.name || `Enrollment #${row.enrollment_id}`}</IonCardTitle>
                      <IonText color="medium">
                        <p>{row.participant?.participant_code || 'No participant ID'}</p>
                      </IonText>
                    </IonCardHeader>
                    <IonCardContent>
                      <div className="record-meta">
                        <span>Avg Pre: {formatScore(row.avg_pre_score)}</span>
                        <span>Avg Post: {formatScore(row.avg_post_score)}</span>
                        <span>Final: {formatScore(row.final_score)}</span>
                      </div>
                    </IonCardContent>
                  </IonCard>
                ))}
              </div>
            </div>
          </section>
        ) : null}

        {activeView === 'closeout' ? (
          <section className="workflow-panel">
            <IonItem lines="none" className="form-field">
              <IonLabel position="stacked">Training Event Status</IonLabel>
              <IonSelect value={closeoutStatus} onIonChange={(event) => setCloseoutStatus(String(event.detail.value || ''))}>
                {closeout.statuses.map((status) => (
                  <IonSelectOption key={status} value={status}>
                    {status}
                  </IonSelectOption>
                ))}
              </IonSelect>
            </IonItem>

            <IonCard>
              <IonCardHeader>
                <IonCardTitle>Final Report</IonCardTitle>
              </IonCardHeader>
              <IonCardContent>
                {closeout.report_url ? (
                  <div className="workflow-closeout-current">
                    <a href={closeout.report_url} target="_blank" rel="noreferrer">
                      Open current report
                    </a>
                    <IonItem lines="none" className="form-field">
                      <IonCheckbox checked={removeReport} onIonChange={(event) => setRemoveReport(Boolean(event.detail.checked))}>
                        Remove current report
                      </IonCheckbox>
                    </IonItem>
                  </div>
                ) : (
                  <IonText color="medium">No report uploaded.</IonText>
                )}
                <label className="workflow-file-input">
                  <span>Upload replacement report</span>
                  <input
                    type="file"
                    accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                    onChange={(event: ChangeEvent<HTMLInputElement>) => setReportFile(event.target.files?.[0] || null)}
                  />
                </label>
                {reportFile ? <IonText color="medium">{reportFile.name}</IonText> : null}
              </IonCardContent>
            </IonCard>

            <IonCard>
              <IonCardHeader>
                <IonCardTitle>Event Pictures</IonCardTitle>
              </IonCardHeader>
              <IonCardContent>
                {closeout.pictures.length > 0 ? (
                  <div className="workflow-picture-grid">
                    {closeout.pictures.map((picture) => (
                      <div className="workflow-picture" key={picture.path}>
                        {picture.url ? <img src={picture.url} alt="Training event" /> : null}
                        <IonCheckbox
                          checked={removePictures.has(picture.path)}
                          onIonChange={(event) => togglePictureRemoval(picture.path, Boolean(event.detail.checked))}
                        >
                          Remove
                        </IonCheckbox>
                      </div>
                    ))}
                  </div>
                ) : (
                  <IonText color="medium">No pictures uploaded.</IonText>
                )}
                <label className="workflow-file-input">
                  <span>Add pictures</span>
                  <input
                    type="file"
                    accept="image/*"
                    multiple
                    onChange={(event: ChangeEvent<HTMLInputElement>) => setPictureFiles(Array.from(event.target.files || []))}
                  />
                </label>
                {pictureFiles.length > 0 ? <IonText color="medium">{pictureFiles.length} picture(s) selected</IonText> : null}
              </IonCardContent>
            </IonCard>

            <IonButton expand="block" onClick={() => void saveCloseout()}>
              Save Closeout
            </IonButton>
          </section>
        ) : null}
      </PageHeader>
    );
  }

  return (
    <PageHeader title="Workflow">
      <IonSearchbar
        debounce={450}
        value={query}
        placeholder="Search training workflow"
        onIonInput={(event) => {
          const value = String(event.detail.value || '');
          setQuery(value);
          void loadEvents(value);
        }}
      />

      {error ? <EmptyState title="Workflow unavailable">{error}</EmptyState> : null}
      {!error && events.length === 0 ? <EmptyState title={loading ? 'Loading workflow' : 'No workflow events found'} /> : null}

      <div className="card-list">
        {events.map((item) => (
          <IonCard key={item.event.id}>
            <IonCardHeader>
              <IonCardTitle>{item.event.event_name}</IonCardTitle>
              <IonText color="medium">
                <p>{[item.event.training?.title, item.event.status].filter(Boolean).join(' - ')}</p>
              </IonText>
            </IonCardHeader>
            <IonCardContent>
              <div className="workflow-counts">
                <span>{item.enrollments_count} enrolled</span>
                <span>{item.pending_join_requests_count} pending</span>
                <span>{item.workshop_count} workshops</span>
              </div>
              <IonButton expand="block" onClick={() => void openEvent(item.event.id)}>
                Open Workflow
              </IonButton>
            </IonCardContent>
          </IonCard>
        ))}
      </div>
    </PageHeader>
  );
}

function SummaryCard({ label, value }: { label: string; value: string | number }) {
  return (
    <IonCard className="metric-card">
      <IonCardHeader>
        <IonCardTitle>{label}</IonCardTitle>
      </IonCardHeader>
      <IonCardContent>
        <strong>{value}</strong>
      </IonCardContent>
    </IonCard>
  );
}

function viewForStep(step: number): WorkflowView {
  if (step === 2) {
    return 'enrollment';
  }

  if (step === 3) {
    return 'workshops';
  }

  if (step === 4) {
    return 'report';
  }

  if (step === 5) {
    return 'closeout';
  }

  return 'event';
}

function closeoutFor(detail: WorkflowDetail): WorkflowCloseout {
  return detail.closeout || {
    statuses: fallbackStatuses,
    report_path: null,
    report_url: null,
    pictures: [],
  };
}

function reportWorkshopsFor(detail: WorkflowDetail): WorkflowReportWorkshop[] {
  if (detail.report_workshops) {
    return detail.report_workshops;
  }

  return detail.workshops.map((workshop) => ({
    workshop_number: workshop.workshop_number,
    start_date: workshop.start_date,
    end_date: workshop.end_date,
    avg_pre_score: null,
    avg_post_score: null,
  }));
}

function emptyDraft(): ScoreDraft {
  return {
    pre_test_score: '',
    mid_test_score: '',
    post_test_score: '',
  };
}

function valueToInput(value: number | null | undefined): string {
  return value !== null && value !== undefined ? String(value) : '';
}

function numericOrNull(value: string): number | null {
  const trimmed = value.trim();

  if (trimmed === '') {
    return null;
  }

  const numeric = Number(trimmed);

  return Number.isFinite(numeric) ? numeric : null;
}

function participantName(enrollment: WorkflowEnrollment): string {
  return enrollment.participant?.name || `Participant #${enrollment.participant_id}`;
}

function completedScores(enrollment: WorkflowEnrollment): string {
  const completed = enrollment.scores.filter((score) => score.pre_test_score !== null && score.post_test_score !== null).length;

  return `${completed}/${enrollment.scores.length}`;
}

function formatScore(value: number | null): string {
  return value === null || value === undefined ? 'N/A' : String(value);
}
