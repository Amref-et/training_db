import {
  IonButton,
  IonIcon,
  IonInput,
  IonItem,
  IonLabel,
  IonList,
  IonSelect,
  IonSelectOption,
  IonSpinner,
  IonTextarea,
  IonText,
} from '@ionic/react';
import {
  briefcaseOutline,
  businessOutline,
  callOutline,
  checkmarkCircleOutline,
  locationOutline,
  personOutline,
  schoolOutline,
} from 'ionicons/icons';
import { FormEvent, useEffect, useMemo, useState } from 'react';

import EmptyState from '../components/EmptyState';
import FieldError from '../components/FieldError';
import PageHeader from '../components/PageHeader';
import {
  ApiOption,
  joinRequestOptions,
  organizationOptions,
  registrationOptions,
  registerParticipant,
  RegistrationOptions,
  TrainingEvent,
} from '../services/api';

const emptyForm = {
  first_name: '',
  father_name: '',
  grandfather_name: '',
  date_of_birth: '',
  age: '',
  region_id: '',
  zone_id: '',
  woreda_id: '',
  organization_id: '',
  gender: '',
  home_phone: '',
  mobile_phone: '',
  email: '',
  profession: '',
  training_event_id: '',
  requested_message: '',
};

export default function RegisterPage() {
  const [options, setOptions] = useState<RegistrationOptions | null>(null);
  const [events, setEvents] = useState<TrainingEvent[]>([]);
  const [organizations, setOrganizations] = useState<ApiOption[]>([]);
  const [form, setForm] = useState<Record<string, string>>(emptyForm);
  const [organizationQuery, setOrganizationQuery] = useState('');
  const [organizationDropdownOpen, setOrganizationDropdownOpen] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  const zones = useMemo(
    () => (options?.zones || []).filter((zone) => !form.region_id || String(zone.region_id) === form.region_id),
    [form.region_id, options]
  );
  const woredas = useMemo(
    () => (options?.woredas || []).filter((woreda) => !form.zone_id || String(woreda.zone_id) === form.zone_id),
    [form.zone_id, options]
  );

  const load = async () => {
    setError(null);
    setLoading(true);

    try {
      const [registration, requestableEvents] = await Promise.all([
        registrationOptions(),
        joinRequestOptions(),
      ]);
      setOptions(registration);
      setEvents(requestableEvents);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Registration options unavailable.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void load();
  }, []);

  useEffect(() => {
    const timeout = window.setTimeout(() => {
      void organizationOptions({
        q: organizationQuery,
        region_id: form.region_id,
        zone_id: form.zone_id,
        woreda_id: form.woreda_id,
      })
        .then(setOrganizations)
        .catch(() => setOrganizations([]));
    }, 250);

    return () => window.clearTimeout(timeout);
  }, [form.region_id, form.woreda_id, form.zone_id, organizationQuery]);

  const update = (key: string, value: string) => {
    setForm((current) => ({ ...current, [key]: value }));
  };

  const updateRegion = (value: string) => {
    setForm((current) => ({
      ...current,
      region_id: value,
      zone_id: '',
      woreda_id: '',
      organization_id: '',
    }));
    setOrganizationQuery('');
    setOrganizationDropdownOpen(false);
  };

  const updateZone = (value: string) => {
    setForm((current) => ({
      ...current,
      zone_id: value,
      woreda_id: '',
      organization_id: '',
    }));
    setOrganizationQuery('');
    setOrganizationDropdownOpen(false);
  };

  const updateWoreda = (value: string) => {
    setForm((current) => ({
      ...current,
      woreda_id: value,
      organization_id: '',
    }));
    setOrganizationQuery('');
    setOrganizationDropdownOpen(false);
  };

  const updateOrganizationQuery = (value: string) => {
    setOrganizationQuery(value);
    setOrganizationDropdownOpen(true);

    setForm((current) => ({
      ...current,
      organization_id: '',
    }));
  };

  const selectOrganization = (option: ApiOption) => {
    update('organization_id', String(option.value));
    setOrganizationQuery(option.label);

    setForm((current) => ({
      ...current,
      organization_id: String(option.value),
      region_id: option.region_id ? String(option.region_id) : current.region_id,
      zone_id: option.zone_id ? String(option.zone_id) : current.zone_id,
      woreda_id: option.woreda_id ? String(option.woreda_id) : current.woreda_id,
    }));
    setOrganizationDropdownOpen(false);
  };

  const submit = async (event?: FormEvent) => {
    event?.preventDefault();
    setError(null);
    setNotice(null);

    if (!form.organization_id) {
      setError('Select an organization from the dropdown.');
      setOrganizationDropdownOpen(true);

      return;
    }

    setSubmitting(true);

    const payload = Object.fromEntries(
      Object.entries(form).filter(([, value]) => value !== '')
    );

    try {
      const response = await registerParticipant(payload);
      setNotice(response.message || 'Registration submitted.');
      setForm(emptyForm);
      setOrganizationQuery('');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Registration failed.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <PageHeader title="Register">
      {error && !options ? <EmptyState title="Registration unavailable">{error}</EmptyState> : null}
      {loading ? (
        <div className="registration-loading">
          <IonSpinner name="crescent" />
          <IonText>Loading registration form...</IonText>
        </div>
      ) : null}

      {options ? (
        <form className="registration-form" onSubmit={(event) => void submit(event)}>
          <section className="registration-hero">
            <div>
              <span>Participant Registration</span>
              <h1>New participant profile</h1>
              <p>Capture identity, organization, contact, and training request details.</p>
            </div>
          </section>

          <section className="registration-section">
            <div className="registration-section-heading">
              <IonIcon icon={personOutline} aria-hidden="true" />
              <IonText>
                <h2>Identity</h2>
                <p>Participant name and demographic details</p>
              </IonText>
            </div>
            <div className="registration-grid">
              <IonItem className="form-field" lines="none">
                <IonLabel position="stacked">First Name</IonLabel>
                <IonInput value={form.first_name} onIonInput={(event) => update('first_name', valueOf(event))} required />
              </IonItem>
              <IonItem className="form-field" lines="none">
                <IonLabel position="stacked">Father Name</IonLabel>
                <IonInput value={form.father_name} onIonInput={(event) => update('father_name', valueOf(event))} required />
              </IonItem>
              <IonItem className="form-field span-2" lines="none">
                <IonLabel position="stacked">Grandfather Name</IonLabel>
                <IonInput
                  value={form.grandfather_name}
                  onIonInput={(event) => update('grandfather_name', valueOf(event))}
                  required
                />
              </IonItem>
              <IonItem className="form-field" lines="none">
                <IonLabel position="stacked">Date of Birth</IonLabel>
                <IonInput
                  type="date"
                  value={form.date_of_birth}
                  onIonInput={(event) => update('date_of_birth', valueOf(event))}
                />
              </IonItem>
              <IonItem className="form-field" lines="none">
                <IonLabel position="stacked">Age</IonLabel>
                <IonInput
                  inputmode="numeric"
                  value={form.age}
                  onIonInput={(event) => update('age', valueOf(event))}
                />
              </IonItem>
              <IonItem className="form-field span-2" lines="none">
                <IonLabel position="stacked">Gender</IonLabel>
                <IonSelect value={form.gender} onIonChange={(event) => update('gender', String(event.detail.value || ''))}>
                  <IonSelectOption value="female">Female</IonSelectOption>
                  <IonSelectOption value="male">Male</IonSelectOption>
                </IonSelect>
              </IonItem>
            </div>
          </section>

          <section className="registration-section">
            <div className="registration-section-heading">
              <IonIcon icon={businessOutline} aria-hidden="true" />
              <IonText>
                <h2>Organization</h2>
                <p>Workplace and professional role</p>
              </IonText>
            </div>
            <div className="registration-grid">
              <div className="organization-combobox span-2">
                <IonItem className="form-field" lines="none">
                  <IonLabel position="stacked">Organization</IonLabel>
                  <IonInput
                    value={organizationQuery}
                    onIonFocus={() => setOrganizationDropdownOpen(true)}
                    onIonBlur={() => window.setTimeout(() => setOrganizationDropdownOpen(false), 140)}
                    onIonInput={(event) => updateOrganizationQuery(valueOf(event))}
                    required
                  />
                </IonItem>
                {organizationDropdownOpen ? (
                  <IonList inset className="organization-dropdown">
                    {organizations.length > 0 ? (
                      organizations.map((option) => (
                        <IonItem button key={option.value} onClick={() => selectOrganization(option)}>
                          <IonIcon icon={businessOutline} slot="start" aria-hidden="true" />
                          <IonLabel>
                            <h2>{option.label}</h2>
                            {option.hint ? <p>{option.hint}</p> : null}
                          </IonLabel>
                        </IonItem>
                      ))
                    ) : (
                      <IonItem>
                        <IonLabel>
                          <h2>No organizations found</h2>
                          <p>Type to search or adjust the location filters.</p>
                        </IonLabel>
                      </IonItem>
                    )}
                  </IonList>
                ) : null}
              </div>

              <IonItem className="form-field span-2" lines="none">
                <IonIcon icon={briefcaseOutline} slot="start" aria-hidden="true" />
                <IonLabel position="stacked">Profession</IonLabel>
                <IonSelect value={form.profession} onIonChange={(event) => update('profession', String(event.detail.value || ''))}>
                  {options.professions.map((profession) => (
                    <IonSelectOption value={profession.name} key={profession.name}>
                      {profession.name}
                    </IonSelectOption>
                  ))}
                </IonSelect>
              </IonItem>
            </div>
          </section>

          <section className="registration-section">
            <div className="registration-section-heading">
              <IonIcon icon={locationOutline} aria-hidden="true" />
              <IonText>
                <h2>Location</h2>
                <p>Administrative hierarchy for participant placement</p>
              </IonText>
            </div>
            <div className="registration-grid">
              <IonItem className="form-field" lines="none">
                <IonLabel position="stacked">Region</IonLabel>
                <IonSelect value={form.region_id} onIonChange={(event) => updateRegion(String(event.detail.value || ''))}>
                  {options.regions.map((region) => (
                    <IonSelectOption value={String(region.id)} key={region.id}>
                      {region.name}
                    </IonSelectOption>
                  ))}
                </IonSelect>
              </IonItem>

              <IonItem className="form-field" lines="none">
                <IonLabel position="stacked">Zone</IonLabel>
                <IonSelect value={form.zone_id} onIonChange={(event) => updateZone(String(event.detail.value || ''))}>
                  {zones.map((zone) => (
                    <IonSelectOption value={String(zone.id)} key={zone.id}>
                      {zone.name}
                    </IonSelectOption>
                  ))}
                </IonSelect>
              </IonItem>

              <IonItem className="form-field span-2" lines="none">
                <IonLabel position="stacked">Woreda</IonLabel>
                <IonSelect value={form.woreda_id} onIonChange={(event) => updateWoreda(String(event.detail.value || ''))}>
                  {woredas.map((woreda) => (
                    <IonSelectOption value={String(woreda.id)} key={woreda.id}>
                      {woreda.name}
                    </IonSelectOption>
                  ))}
                </IonSelect>
              </IonItem>
            </div>
          </section>

          <section className="registration-section">
            <div className="registration-section-heading">
              <IonIcon icon={callOutline} aria-hidden="true" />
              <IonText>
                <h2>Contact</h2>
                <p>Primary phone and optional contact channels</p>
              </IonText>
            </div>
            <div className="registration-grid">
              <IonItem className="form-field span-2" lines="none">
                <IonLabel position="stacked">Mobile Phone</IonLabel>
                <IonInput
                  inputmode="tel"
                  value={form.mobile_phone}
                  onIonInput={(event) => update('mobile_phone', valueOf(event))}
                  required
                />
              </IonItem>
              <IonItem className="form-field" lines="none">
                <IonLabel position="stacked">Home Phone</IonLabel>
                <IonInput inputmode="tel" value={form.home_phone} onIonInput={(event) => update('home_phone', valueOf(event))} />
              </IonItem>
              <IonItem className="form-field" lines="none">
                <IonLabel position="stacked">Email</IonLabel>
                <IonInput
                  inputmode="email"
                  type="email"
                  value={form.email}
                  onIonInput={(event) => update('email', valueOf(event))}
                />
              </IonItem>
            </div>
          </section>

          <section className="registration-section">
            <div className="registration-section-heading">
              <IonIcon icon={schoolOutline} aria-hidden="true" />
              <IonText>
                <h2>Training Request</h2>
                <p>Optional enrolment request for an available event</p>
              </IonText>
            </div>
            <div className="registration-grid">
              <IonItem className="form-field span-2" lines="none">
                <IonLabel position="stacked">Training Event</IonLabel>
                <IonSelect
                  value={form.training_event_id}
                  onIonChange={(event) => update('training_event_id', String(event.detail.value || ''))}
                >
                  {events.map((event) => (
                    <IonSelectOption value={String(event.id)} key={event.id}>
                      {event.event_name}
                    </IonSelectOption>
                  ))}
                </IonSelect>
              </IonItem>
              <IonItem className="form-field span-2" lines="none">
                <IonLabel position="stacked">Join Request Message</IonLabel>
                <IonTextarea
                  rows={4}
                  value={form.requested_message}
                  onIonInput={(event) => update('requested_message', valueOf(event))}
                />
              </IonItem>
            </div>
          </section>

          {notice ? (
            <div className="form-notice success">
              <IonIcon icon={checkmarkCircleOutline} aria-hidden="true" />
              <IonText>{notice}</IonText>
            </div>
          ) : null}
          <FieldError message={error} />

          <div className="registration-actions">
            <IonButton type="submit" expand="block" disabled={submitting}>
              {submitting ? <IonSpinner name="crescent" /> : 'Submit Registration'}
            </IonButton>
          </div>
        </form>
      ) : null}
    </PageHeader>
  );
}

function valueOf(event: CustomEvent): string {
  return String(event.detail.value || '');
}
