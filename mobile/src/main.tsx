import { IonApp, setupIonicReact } from '@ionic/react';
import { createRoot } from 'react-dom/client';
import { Redirect, Route } from 'react-router-dom';
import { IonReactRouter } from '@ionic/react-router';

import AppTabs from './components/AppTabs';
import OfflineStatusBanner from './components/OfflineStatusBanner';
import { AppearanceProvider } from './services/appearance';
import { AuthProvider, useAuth } from './services/auth';
import LoginPage from './pages/LoginPage';

import '@ionic/react/css/core.css';
import '@ionic/react/css/normalize.css';
import '@ionic/react/css/structure.css';
import '@ionic/react/css/typography.css';
import '@ionic/react/css/display.css';
import '@ionic/react/css/flex-utils.css';
import './theme/variables.css';
import './theme/app.css';

setupIonicReact({ mode: 'md' });

function AppRoutes() {
  const auth = useAuth();

  if (!auth.ready) {
    return <IonApp />;
  }

  return (
    <IonApp>
      <OfflineStatusBanner />
      <IonReactRouter>
        {auth.user ? (
          <AppTabs />
        ) : (
          <>
            <Route path="/login" component={LoginPage} exact />
            <Redirect to="/login" />
          </>
        )}
      </IonReactRouter>
    </IonApp>
  );
}

createRoot(document.getElementById('root') as HTMLElement).render(
  <AppearanceProvider>
    <AuthProvider>
      <AppRoutes />
    </AuthProvider>
  </AppearanceProvider>
);
