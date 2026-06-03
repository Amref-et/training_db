import { IonButtons, IonContent, IonHeader, IonPage, IonTitle, IonToolbar } from '@ionic/react';
import { ReactNode } from 'react';

import BrandLogo from './BrandLogo';

export default function PageHeader({
  title,
  children,
  actions,
}: {
  title: string;
  children: ReactNode;
  actions?: ReactNode;
}) {
  return (
    <IonPage>
      <IonHeader translucent>
        <IonToolbar>
          <div className="mobile-toolbar-brand" slot="start">
            <BrandLogo compact />
          </div>
          <IonTitle>{title}</IonTitle>
          {actions ? <IonButtons slot="end">{actions}</IonButtons> : null}
        </IonToolbar>
      </IonHeader>
      <IonContent fullscreen className="page-content">
        <div className="screen-shell">{children}</div>
      </IonContent>
    </IonPage>
  );
}
