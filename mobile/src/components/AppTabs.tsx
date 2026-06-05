import {
  IonIcon,
  IonLabel,
  IonRouterOutlet,
  IonTabBar,
  IonTabButton,
  IonTabs,
} from '@ionic/react';
import { calendarOutline, gitNetworkOutline, homeOutline, peopleOutline, personAddOutline } from 'ionicons/icons';
import { Redirect, Route } from 'react-router-dom';

import DashboardPage from '../pages/DashboardPage';
import EventsPage from '../pages/EventsPage';
import ParticipantsPage from '../pages/ParticipantsPage';
import RegisterPage from '../pages/RegisterPage';
import WorkflowPage from '../pages/WorkflowPage';

export default function AppTabs() {
  return (
    <IonTabs>
      <IonRouterOutlet>
        <Route exact path="/tabs/dashboard" component={DashboardPage} />
        <Route exact path="/tabs/participants" component={ParticipantsPage} />
        <Route exact path="/tabs/events" component={EventsPage} />
        <Route exact path="/tabs/workflow" component={WorkflowPage} />
        <Route exact path="/tabs/register" component={RegisterPage} />
        <Route exact path="/login">
          <Redirect to="/tabs/dashboard" />
        </Route>
        <Route exact path="/">
          <Redirect to="/tabs/dashboard" />
        </Route>
      </IonRouterOutlet>
      <IonTabBar slot="bottom">
        <IonTabButton tab="dashboard" href="/tabs/dashboard">
          <IonIcon icon={homeOutline} />
          <IonLabel>Dashboard</IonLabel>
        </IonTabButton>
        <IonTabButton tab="participants" href="/tabs/participants">
          <IonIcon icon={peopleOutline} />
          <IonLabel>Participants</IonLabel>
        </IonTabButton>
        <IonTabButton tab="events" href="/tabs/events">
          <IonIcon icon={calendarOutline} />
          <IonLabel>Events</IonLabel>
        </IonTabButton>
        <IonTabButton tab="workflow" href="/tabs/workflow">
          <IonIcon icon={gitNetworkOutline} />
          <IonLabel>Workflow</IonLabel>
        </IonTabButton>
        <IonTabButton tab="register" href="/tabs/register">
          <IonIcon icon={personAddOutline} />
          <IonLabel>Register</IonLabel>
        </IonTabButton>
      </IonTabBar>
    </IonTabs>
  );
}
