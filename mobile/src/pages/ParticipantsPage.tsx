import {
  IonBadge,
  IonItem,
  IonLabel,
  IonList,
  IonRefresher,
  IonRefresherContent,
  IonSearchbar,
  IonText,
} from '@ionic/react';
import { useEffect, useState } from 'react';
import { useHistory } from 'react-router-dom';

import EmptyState from '../components/EmptyState';
import PageHeader from '../components/PageHeader';
import { Participant, participants } from '../services/api';

export default function ParticipantsPage() {
  const history = useHistory();
  const [items, setItems] = useState<Participant[]>([]);
  const [query, setQuery] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = async (search = query) => {
    setError(null);
    setLoading(true);

    try {
      const response = await participants(search);
      setItems(response.data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Participants unavailable.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void load('');
  }, []);

  const openEventSelection = (participant: Participant) => {
    const params = new URLSearchParams({
      participant_id: String(participant.id),
      participant_name: participant.name,
      mobile_phone: participant.mobile_phone || '',
    });

    if (participant.participant_code) {
      params.set('participant_code', participant.participant_code);
    }

    history.push(`/tabs/events?${params.toString()}`);
  };

  return (
    <PageHeader title="Participants">
      <IonRefresher slot="fixed" onIonRefresh={(event) => load().finally(() => event.detail.complete())}>
        <IonRefresherContent />
      </IonRefresher>

      <IonSearchbar
        debounce={450}
        value={query}
        placeholder="Search name, phone, or code"
        onIonInput={(event) => {
          const value = String(event.detail.value || '');
          setQuery(value);
          void load(value);
        }}
      />

      {error ? <EmptyState title="Participants unavailable">{error}</EmptyState> : null}

      {!error && !loading && items.length === 0 ? <EmptyState title="No participants found" /> : null}

      <IonList inset className="record-list">
        {items.map((participant) => (
          <IonItem key={participant.id} button detail onClick={() => openEventSelection(participant)}>
            <IonLabel>
              <h2>{participant.name}</h2>
              <p>{participant.mobile_phone}</p>
              <IonText color="medium">
                <small>
                  {[participant.organization?.name, participant.profession].filter(Boolean).join(' · ')}
                </small>
              </IonText>
            </IonLabel>
            {participant.participant_code ? <IonBadge color="light">{participant.participant_code}</IonBadge> : null}
          </IonItem>
        ))}
      </IonList>
    </PageHeader>
  );
}
