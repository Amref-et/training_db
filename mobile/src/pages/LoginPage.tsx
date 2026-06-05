import {
  IonButton,
  IonContent,
  IonIcon,
  IonInput,
  IonItem,
  IonLabel,
  IonPage,
  IonSpinner,
  IonText,
} from '@ionic/react';
import { checkmarkCircleOutline, lockClosedOutline, mailOutline, phonePortraitOutline, settingsOutline } from 'ionicons/icons';
import { FormEvent, useEffect, useState } from 'react';
import { useHistory } from 'react-router-dom';

import BrandLogo from '../components/BrandLogo';
import FieldError from '../components/FieldError';
import { getApiBaseUrl, syncQueuedRequests } from '../services/api';
import { useAppearance } from '../services/appearance';
import { useAuth } from '../services/auth';
import { DEFAULT_DEVICE_NAME, getAutomaticDeviceName } from '../services/device';

export default function LoginPage() {
  const auth = useAuth();
  const appearance = useAppearance();
  const history = useHistory();
  const { settings } = appearance;
  const [apiBaseUrl, setApiBaseUrl] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [deviceName, setDeviceName] = useState(DEFAULT_DEVICE_NAME);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    void getApiBaseUrl().then((value) => {
      setApiBaseUrl(value);
      void appearance.refresh(value);
    });

    let cancelled = false;

    void getAutomaticDeviceName().then((detectedDeviceName) => {
      if (cancelled) {
        return;
      }

      setDeviceName((currentDeviceName) =>
        currentDeviceName === DEFAULT_DEVICE_NAME ? detectedDeviceName : currentDeviceName
      );
    });

    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    if (!apiBaseUrl) {
      return;
    }

    const timeout = window.setTimeout(() => {
      void appearance.refresh(apiBaseUrl);
    }, 500);

    return () => window.clearTimeout(timeout);
  }, [apiBaseUrl]);

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    setError(null);

    const normalizedEmail = email.trim();
    const normalizedApiBaseUrl = apiBaseUrl.trim();
    const normalizedDeviceName = deviceName.trim() || DEFAULT_DEVICE_NAME;

    if (!normalizedApiBaseUrl) {
      setError('Enter the Laravel URL before signing in.');

      return;
    }

    if (!normalizedEmail || !password) {
      setError('Enter your email and password.');

      return;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalizedEmail)) {
      setError('Enter a valid email address.');

      return;
    }

    setSubmitting(true);

    try {
      await auth.login({
        email: normalizedEmail,
        password,
        deviceName: normalizedDeviceName,
        apiBaseUrl: normalizedApiBaseUrl,
      });
      setEmail(normalizedEmail);
      setApiBaseUrl(normalizedApiBaseUrl);
      setDeviceName(normalizedDeviceName);
      await appearance.refresh(normalizedApiBaseUrl);
      await syncQueuedRequests();
      history.replace('/tabs/dashboard');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to sign in.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <IonPage>
      <IonContent fullscreen className="login-screen">
        <form className="login-panel" onSubmit={submit}>
          <section className="brand-block">
            <BrandLogo />
            <IonText>
              <span className="login-eyebrow">{settings.login.eyebrow}</span>
              <h1>{settings.login.title || settings.site.name}</h1>
              <p>{settings.site.tagline || settings.login.subtitle}</p>
            </IonText>
          </section>

          <section className="login-card" aria-label="Sign in">
            <IonText className="login-intro">
              <h2>{settings.login.form_title || 'Welcome back'}</h2>
              <p>{settings.login.form_subtitle || settings.login.subtitle}</p>
            </IonText>

            <div className="login-fields">
              <IonItem className="login-field" lines="none">
                <IonIcon icon={mailOutline} slot="start" aria-hidden="true" />
                <IonLabel position="stacked">{settings.login.email_label}</IonLabel>
                <IonInput
                  autocomplete="email"
                  inputmode="email"
                  type="email"
                  value={email}
                  onIonInput={(event) => setEmail(String(event.detail.value || ''))}
                  required
                />
              </IonItem>

              <IonItem className="login-field" lines="none">
                <IonIcon icon={lockClosedOutline} slot="start" aria-hidden="true" />
                <IonLabel position="stacked">{settings.login.password_label}</IonLabel>
                <IonInput
                  autocomplete="current-password"
                  type="password"
                  value={password}
                  onIonInput={(event) => setPassword(String(event.detail.value || ''))}
                  required
                />
              </IonItem>
            </div>

            <FieldError message={error} />

            <IonButton className="login-submit" type="submit" expand="block" disabled={submitting}>
              {submitting ? <IonSpinner name="crescent" /> : settings.login.submit_label}
            </IonButton>

            <details className="login-connection">
              <summary>
                <IonIcon icon={settingsOutline} aria-hidden="true" />
                Connection
              </summary>
              <IonItem className="login-field" lines="none">
                <IonIcon icon={settingsOutline} slot="start" aria-hidden="true" />
                <IonLabel position="stacked">Laravel URL</IonLabel>
                <IonInput
                  autocomplete="url"
                  inputmode="url"
                  placeholder="https://et-dhis.amref.org/hil2"
                  value={apiBaseUrl}
                  onIonInput={(event) => setApiBaseUrl(String(event.detail.value || ''))}
                />
              </IonItem>

              <IonItem className="login-field" lines="none">
                <IonIcon icon={phonePortraitOutline} slot="start" aria-hidden="true" />
                <IonLabel position="stacked">Detected Device</IonLabel>
                <IonInput
                  value={deviceName}
                  onIonInput={(event) => setDeviceName(String(event.detail.value || ''))}
                />
              </IonItem>
            </details>
          </section>

          <div className="login-features">
            {[settings.login.feature_1, settings.login.feature_2, settings.login.feature_3]
              .filter((feature): feature is string => typeof feature === 'string' && feature !== '')
              .map((feature) => (
                <span key={feature}>
                  <IonIcon icon={checkmarkCircleOutline} aria-hidden="true" />
                  {feature}
                </span>
              ))}
          </div>
        </form>
      </IonContent>
    </IonPage>
  );
}
