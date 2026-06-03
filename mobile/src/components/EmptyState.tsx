import { IonIcon, IonText } from '@ionic/react';
import { alertCircleOutline } from 'ionicons/icons';
import { ReactNode } from 'react';

export default function EmptyState({ title, children }: { title: string; children?: ReactNode }) {
  return (
    <div className="empty-state">
      <IonIcon icon={alertCircleOutline} aria-hidden="true" />
      <IonText>
        <h2>{title}</h2>
        {children ? <p>{children}</p> : null}
      </IonText>
    </div>
  );
}
