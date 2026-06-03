import {
  IonButton,
  IonCard,
  IonCardContent,
  IonCardHeader,
  IonCardTitle,
  IonIcon,
  IonRefresher,
  IonRefresherContent,
  IonText,
} from '@ionic/react';
import { logOutOutline, refreshOutline } from 'ionicons/icons';
import { useEffect, useMemo, useState } from 'react';

import EmptyState from '../components/EmptyState';
import PageHeader from '../components/PageHeader';
import { dashboard } from '../services/api';
import { useAuth } from '../services/auth';

export default function DashboardPage() {
  const auth = useAuth();
  const [summary, setSummary] = useState<Record<string, unknown> | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const cards = useMemo(() => {
    if (!summary) {
      return [];
    }

    return Object.entries(summary)
      .filter(([, value]) => ['number', 'string'].includes(typeof value))
      .slice(0, 6);
  }, [summary]);

  const load = async () => {
    setError(null);
    setLoading(true);

    try {
      const response = await dashboard();
      setSummary(response.summary || null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Dashboard unavailable.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void load();
  }, []);

  return (
    <PageHeader
      title="Dashboard"
      actions={
        <IonButton fill="clear" onClick={() => void auth.logout()} aria-label="Sign out">
          <IonIcon icon={logOutOutline} />
        </IonButton>
      }
    >
      <IonRefresher slot="fixed" onIonRefresh={(event) => load().finally(() => event.detail.complete())}>
        <IonRefresherContent />
      </IonRefresher>

      <div className="section-heading">
        <IonText>
          <h1>{auth.user?.name}</h1>
          <p>{auth.user?.email}</p>
        </IonText>
        <IonButton fill="outline" size="small" onClick={() => void load()} disabled={loading}>
          <IonIcon icon={refreshOutline} slot="start" />
          Refresh
        </IonButton>
      </div>

      {error ? (
        <EmptyState title="Dashboard unavailable">{error}</EmptyState>
      ) : (
        <div className="metric-grid">
          {cards.map(([label, value]) => (
            <IonCard className="metric-card" key={label}>
              <IonCardHeader>
                <IonCardTitle>{formatLabel(label)}</IonCardTitle>
              </IonCardHeader>
              <IonCardContent>
                <strong>{String(value)}</strong>
              </IonCardContent>
            </IonCard>
          ))}
          {!loading && cards.length === 0 ? <EmptyState title="No dashboard metrics" /> : null}
        </div>
      )}
    </PageHeader>
  );
}

function formatLabel(value: string): string {
  return value
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (character) => character.toUpperCase());
}
