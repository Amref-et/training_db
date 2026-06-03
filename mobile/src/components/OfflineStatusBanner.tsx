import { IonButton, IonIcon, IonText } from '@ionic/react';
import { cloudDoneOutline, cloudOfflineOutline, syncOutline, warningOutline } from 'ionicons/icons';
import { useEffect, useState } from 'react';

import {
  getOfflineSnapshot,
  offlineStatusEventName,
  OfflineSnapshot,
  syncQueuedRequests,
} from '../services/api';

export default function OfflineStatusBanner() {
  const [snapshot, setSnapshot] = useState<OfflineSnapshot>({
    online: true,
    pending: 0,
    syncing: false,
    lastSyncAt: null,
    lastError: null,
  });

  const refresh = async () => {
    setSnapshot(await getOfflineSnapshot());
  };

  const sync = async () => {
    await syncQueuedRequests();
    await refresh();
  };

  useEffect(() => {
    void sync();

    const handleOnline = () => void sync();
    const handleOffline = () => void refresh();
    const handleStatus = (event: Event) => {
      const detail = (event as CustomEvent<OfflineSnapshot>).detail;

      if (detail) {
        setSnapshot(detail);
      }
    };

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
    window.addEventListener(offlineStatusEventName(), handleStatus);

    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
      window.removeEventListener(offlineStatusEventName(), handleStatus);
    };
  }, []);

  if (snapshot.online && snapshot.pending === 0 && !snapshot.syncing && !snapshot.lastError) {
    return null;
  }

  const icon = snapshot.syncing
    ? syncOutline
    : snapshot.lastError
      ? warningOutline
      : snapshot.online
        ? cloudDoneOutline
        : cloudOfflineOutline;
  const message = snapshot.syncing
    ? 'Syncing offline work...'
    : snapshot.lastError
      ? `Sync issue: ${snapshot.lastError}`
      : snapshot.online
        ? `${snapshot.pending} pending item${snapshot.pending === 1 ? '' : 's'} ready to sync`
        : `${snapshot.pending} pending item${snapshot.pending === 1 ? '' : 's'} saved offline`;

  return (
    <div className={`offline-status ${snapshot.online ? 'online' : 'offline'}`}>
      <IonIcon icon={icon} aria-hidden="true" />
      <IonText>{message}</IonText>
      {snapshot.online && snapshot.pending > 0 && !snapshot.syncing ? (
        <IonButton size="small" fill="clear" onClick={() => void sync()}>
          Sync
        </IonButton>
      ) : null}
    </div>
  );
}
