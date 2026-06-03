import {
  IonButton,
  IonCard,
  IonCardContent,
  IonCardHeader,
  IonCardTitle,
  IonInput,
  IonItem,
  IonLabel,
  IonList,
  IonModal,
  IonSearchbar,
  IonTextarea,
  IonText,
} from '@ionic/react';
import { useEffect, useMemo, useState } from 'react';
import { useHistory, useLocation } from 'react-router-dom';

import EmptyState from '../components/EmptyState';
import FieldError from '../components/FieldError';
import PageHeader from '../components/PageHeader';
import {
  ApiOption,
  enrollParticipant,
  participantOptions,
  submitJoinRequest,
  TrainingEvent,
  trainingEvents,
} from '../services/api';

type SelectedParticipant = {
  id: number | null;
  name: string;
  mobilePhone: string;
  code: string | null;
};

export default function EventsPage() {
  const history = useHistory();
  const location = useLocation();
  const [events, setEvents] = useState<TrainingEvent[]>([]);
  const [query, setQuery] = useState('');
  const [activeEvent, setActiveEvent] = useState<TrainingEvent | null>(null);
  const [participantQuery, setParticipantQuery] = useState('');
  const [participantMatches, setParticipantMatches] = useState<ApiOption[]>([]);
  const [participantId, setParticipantId] = useState<number | null>(null);
  const [participantName, setParticipantName] = useState('');
  const [mobilePhone, setMobilePhone] = useState('');
  const [message, setMessage] = useState('');
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const selectedParticipant = useMemo(() => selectedParticipantFromSearch(location.search), [location.search]);

  const load = async (search = query) => {
    setError(null);

    try {
      const response = await trainingEvents(search);
      setEvents(response.data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Training events unavailable.');
    }
  };

  useEffect(() => {
    void load('');
  }, []);

  const searchParticipants = async (value: string) => {
    setParticipantQuery(value);
    setParticipantName(value);
    setParticipantId(null);

    if (value.trim().length < 2) {
      setParticipantMatches([]);

      return;
    }

    setParticipantMatches(await participantOptions(value));
  };

  const openJoinModal = (event: TrainingEvent) => {
    setError(null);
    setActiveEvent(event);

    if (selectedParticipant) {
      setParticipantId(selectedParticipant.id);
      setParticipantName(selectedParticipant.name);
      setParticipantQuery(selectedParticipant.name);
      setMobilePhone(selectedParticipant.mobilePhone);
      setParticipantMatches([]);

      return;
    }

    clearParticipantFields();
  };

  const submit = async () => {
    if (!activeEvent) {
      return;
    }

    setError(null);
    setNotice(null);

    try {
      if (selectedParticipant) {
        if (!participantId) {
          setError('Selected participant is missing an identifier.');

          return;
        }

        const response = await enrollParticipant(activeEvent.id, participantId);

        setNotice(response.message || 'Participant enrolled successfully.');
        const syncedEvent = 'event' in response.data ? response.data.event : null;

        if (syncedEvent) {
          setEvents((currentEvents) =>
            currentEvents.map((event) => (event.id === activeEvent.id ? syncedEvent : event))
          );
        }
        closeModal();
        history.replace('/tabs/events');

        return;
      }

      const response = await submitJoinRequest({
        training_event_id: activeEvent.id,
        participant_id: participantId,
        participant_name: participantName,
        mobile_phone: mobilePhone,
        requested_message: message,
      });
      setNotice(response.message || 'Join request submitted.');
      closeModal();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Join request failed.');
    }
  };

  const closeModal = () => {
    setActiveEvent(null);
    clearParticipantFields();
  };

  const clearParticipantFields = () => {
    setParticipantQuery('');
    setParticipantMatches([]);
    setParticipantId(null);
    setParticipantName('');
    setMobilePhone('');
    setMessage('');
  };

  const clearSelectedParticipant = () => {
    history.replace('/tabs/events');
    clearParticipantFields();
  };

  return (
    <PageHeader title="Events">
      {selectedParticipant ? (
        <div className="selected-participant-banner">
          <IonText>
            <span>Enrolling participant</span>
            <h2>{selectedParticipant.name}</h2>
            <p>
              {[selectedParticipant.mobilePhone, selectedParticipant.code].filter(Boolean).join(' - ')}
            </p>
          </IonText>
          <IonButton fill="clear" size="small" onClick={clearSelectedParticipant}>
            Clear
          </IonButton>
        </div>
      ) : null}

      <IonSearchbar
        debounce={450}
        value={query}
        placeholder="Search events"
        onIonInput={(event) => {
          const value = String(event.detail.value || '');
          setQuery(value);
          void load(value);
        }}
      />

      {notice ? <IonText color="success">{notice}</IonText> : null}
      {error && !activeEvent ? <EmptyState title="Events unavailable">{error}</EmptyState> : null}
      {!error && events.length === 0 ? <EmptyState title="No training events found" /> : null}

      <div className="card-list">
        {events.map((event) => (
          <IonCard key={event.id}>
            <IonCardHeader>
              <IonCardTitle>{event.event_name}</IonCardTitle>
              <IonText color="medium">
                <p>{[event.training?.title, event.training_city, event.status].filter(Boolean).join(' · ')}</p>
              </IonText>
            </IonCardHeader>
            <IonCardContent>
              <div className="record-meta">
                <span>{event.start_date}</span>
                <span>{event.end_date}</span>
                {event.participants_count !== undefined ? <span>{event.participants_count} participants</span> : null}
              </div>
              <IonButton expand="block" fill="outline" onClick={() => openJoinModal(event)}>
                {selectedParticipant ? 'Request Enrolment' : 'Request Join'}
              </IonButton>
            </IonCardContent>
          </IonCard>
        ))}
      </div>

      <IonModal isOpen={Boolean(activeEvent)} onDidDismiss={closeModal}>
        <PageHeader title={selectedParticipant ? 'Enrol Participant' : 'Join Request'}>
          <IonText>
            <h1>{activeEvent?.event_name}</h1>
          </IonText>
          {selectedParticipant ? (
            <div className="selected-enrollment-summary">
              <IonText>
                <span>Selected participant</span>
                <h2>{selectedParticipant.name}</h2>
                <p>
                  {[selectedParticipant.mobilePhone, selectedParticipant.code].filter(Boolean).join(' - ')}
                </p>
              </IonText>
            </div>
          ) : (
            <>
              <IonItem lines="full">
                <IonLabel position="stacked">Participant</IonLabel>
                <IonInput
                  value={participantQuery}
                  onIonInput={(event) => void searchParticipants(String(event.detail.value || ''))}
                  required
                />
              </IonItem>
              {participantMatches.length > 0 ? (
                <IonList inset className="record-list">
                  {participantMatches.map((option) => (
                    <IonItem
                      key={option.value}
                      button
                      onClick={() => {
                        setParticipantId(option.value);
                        setParticipantName(option.label);
                        setParticipantQuery(option.label);
                        setMobilePhone(option.mobile_phone || '');
                        setParticipantMatches([]);
                      }}
                    >
                      <IonLabel>
                        <h2>{option.label}</h2>
                        <p>{option.hint}</p>
                      </IonLabel>
                    </IonItem>
                  ))}
                </IonList>
              ) : null}
              <IonItem lines="full">
                <IonLabel position="stacked">Registered Mobile Phone</IonLabel>
                <IonInput
                  inputmode="tel"
                  value={mobilePhone}
                  onIonInput={(event) => setMobilePhone(String(event.detail.value || ''))}
                  required
                />
              </IonItem>
              <IonItem lines="full">
                <IonLabel position="stacked">Message</IonLabel>
                <IonTextarea
                  rows={4}
                  value={message}
                  onIonInput={(event) => setMessage(String(event.detail.value || ''))}
                />
              </IonItem>
            </>
          )}
          <FieldError message={error} />
          <div className="button-row">
            <IonButton fill="outline" onClick={closeModal}>
              Cancel
            </IonButton>
            <IonButton onClick={() => void submit()}>{selectedParticipant ? 'Enroll' : 'Submit'}</IonButton>
          </div>
        </PageHeader>
      </IonModal>
    </PageHeader>
  );
}

function selectedParticipantFromSearch(search: string): SelectedParticipant | null {
  const params = new URLSearchParams(search);
  const name = (params.get('participant_name') || '').trim();

  if (!name) {
    return null;
  }

  const id = Number(params.get('participant_id'));

  return {
    id: Number.isFinite(id) && id > 0 ? id : null,
    name,
    mobilePhone: params.get('mobile_phone') || '',
    code: params.get('participant_code') || null,
  };
}
