import { createContext, ReactNode, useContext, useEffect, useMemo, useState } from 'react';

import { appearance, AppearanceSettings, getApiBaseUrl } from './api';

const defaultAppearance: AppearanceSettings = {
  site: {
    name: 'Amref Training Database',
    tagline: 'High-impact learning and implementation platform.',
  },
  logos: {
    header_url: null,
    footer_url: null,
    favicon_url: null,
    header_height: 56,
  },
  colors: {
    header_background: '#ffffff',
    header_text: '#0f172a',
    header_link: '#334155',
    body_background: '#f8fafc',
    body_text: '#0f172a',
    body_panel: '#ffffff',
    body_accent: '#0f766e',
    footer_background: '#0f172a',
    footer_text: '#e2e8f0',
    footer_link: '#cbd5e1',
  },
  radii: {
    sm: 10,
    md: 14,
    lg: 18,
    xl: 24,
    pill: 999,
  },
  login: {
    eyebrow: 'Admin Access',
    title: null,
    subtitle: 'Use your administrator account to manage training, participants, projects, and reporting.',
    background_start: '#082f49',
    background_end: '#0f766e',
    background_accent: '#d97706',
    card_background: '#ffffff',
    form_title: 'Welcome back',
    form_subtitle: 'Enter your credentials to continue to the administrative workspace.',
    email_label: 'Email',
    password_label: 'Password',
    submit_label: 'Login',
    feature_1: null,
    feature_2: null,
    feature_3: null,
  },
};

type AppearanceState = {
  settings: AppearanceSettings;
  refresh: (baseUrl?: string | null) => Promise<void>;
};

const AppearanceContext = createContext<AppearanceState | undefined>(undefined);

export function AppearanceProvider({ children }: { children: ReactNode }) {
  const [settings, setSettings] = useState<AppearanceSettings>(defaultAppearance);

  const refresh = async (baseUrl?: string | null) => {
    try {
      const currentBaseUrl = baseUrl || (await getApiBaseUrl());
      const nextSettings = await appearance(currentBaseUrl);
      setSettings(nextSettings);
      applyAppearance(nextSettings);
    } catch {
      applyAppearance(defaultAppearance);
    }
  };

  useEffect(() => {
    applyAppearance(defaultAppearance);
    void refresh();
  }, []);

  const value = useMemo(() => ({ settings, refresh }), [settings]);

  return <AppearanceContext.Provider value={value}>{children}</AppearanceContext.Provider>;
}

export function useAppearance(): AppearanceState {
  const value = useContext(AppearanceContext);

  if (!value) {
    throw new Error('useAppearance must be used inside AppearanceProvider');
  }

  return value;
}

export function brandInitials(name: string): string {
  return name
    .split(/\s+/)
    .map((part) => part[0])
    .join('')
    .slice(0, 3)
    .toUpperCase();
}

function applyAppearance(settings: AppearanceSettings): void {
  const root = document.documentElement;
  const primaryRgb = hexToRgb(settings.colors.body_accent);
  const secondaryRgb = hexToRgb(settings.colors.header_link);
  const bodyText = ensureContrast(settings.colors.body_text, settings.colors.body_background);
  const panelText = ensureContrast(settings.colors.body_text, settings.colors.body_panel);
  const toolbarText = ensureContrast(settings.colors.header_text, settings.colors.header_background);
  const tabText = ensureContrast(settings.colors.header_link, settings.colors.header_background, 3);
  const tabSelected = ensureContrast(settings.colors.body_accent, settings.colors.header_background, 3);
  const mutedText = mutedFor(settings.colors.body_panel);
  const loginCardText = ensureContrast(settings.colors.body_text, settings.login.card_background);
  const primaryContrast = readableOn(settings.colors.body_accent);

  setCss('--ion-color-primary', settings.colors.body_accent);
  setCss('--ion-color-primary-rgb', primaryRgb);
  setCss('--ion-color-primary-contrast', primaryContrast);
  setCss('--ion-color-primary-contrast-rgb', hexToRgb(primaryContrast));
  setCss('--ion-color-primary-shade', shade(settings.colors.body_accent, -12));
  setCss('--ion-color-primary-tint', shade(settings.colors.body_accent, 12));
  setCss('--ion-color-secondary', settings.colors.header_link);
  setCss('--ion-color-secondary-rgb', secondaryRgb);
  setCss('--ion-background-color', settings.colors.body_background);
  setCss('--ion-text-color', bodyText);
  setCss('--ion-card-background', settings.colors.body_panel);
  setCss('--ion-toolbar-background', settings.colors.header_background);
  setCss('--ion-tab-bar-background', settings.colors.header_background);
  setCss('--hil-mobile-toolbar-text', toolbarText);
  setCss('--hil-mobile-tab-bg', settings.colors.header_background);
  setCss('--hil-mobile-tab-text', tabText);
  setCss('--hil-mobile-tab-selected', tabSelected);
  setCss('--hil-mobile-panel', settings.colors.body_panel);
  setCss('--hil-mobile-panel-text', panelText);
  setCss('--hil-mobile-muted', mutedText);
  setCss('--hil-mobile-card-title', mutedText);
  setCss('--hil-mobile-footer-bg', settings.colors.footer_background);
  setCss('--hil-mobile-login-start', settings.login.background_start);
  setCss('--hil-mobile-login-end', settings.login.background_end);
  setCss('--hil-mobile-login-accent', settings.login.background_accent);
  setCss('--hil-mobile-login-card', settings.login.card_background);
  setCss('--hil-mobile-login-card-text', loginCardText);
  setCss('--hil-radius-sm', `${settings.radii.sm}px`);
  setCss('--hil-radius-md', `${settings.radii.md}px`);
  setCss('--hil-radius-lg', `${settings.radii.lg}px`);
  setCss('--hil-radius-xl', `${settings.radii.xl}px`);
  setCss('--hil-radius-pill', `${settings.radii.pill}px`);

  document.title = settings.site.name;

  if (settings.logos.favicon_url) {
    let favicon = document.querySelector<HTMLLinkElement>('link[rel="icon"]');

    if (!favicon) {
      favicon = document.createElement('link');
      favicon.rel = 'icon';
      document.head.appendChild(favicon);
    }

    favicon.href = settings.logos.favicon_url;
  }

  function setCss(name: string, value: string): void {
    root.style.setProperty(name, value);
  }
}

function hexToRgb(hex: string): string {
  const normalized = normalizeHex(hex).replace('#', '');
  const value = normalized.length === 3
    ? normalized.split('').map((part) => part + part).join('')
    : normalized.padEnd(6, '0').slice(0, 6);

  return [
    parseInt(value.slice(0, 2), 16),
    parseInt(value.slice(2, 4), 16),
    parseInt(value.slice(4, 6), 16),
  ].join(',');
}

function shade(hex: string, amount: number): string {
  const [red, green, blue] = hexToRgb(hex).split(',').map(Number);

  return `#${[red, green, blue]
    .map((value) => Math.max(0, Math.min(255, value + amount)).toString(16).padStart(2, '0'))
    .join('')}`;
}

function ensureContrast(foreground: string, background: string, minimumRatio = 4.5): string {
  const fg = normalizeHex(foreground);
  const bg = normalizeHex(background);

  return contrastRatio(fg, bg) >= minimumRatio ? fg : readableOn(bg);
}

function readableOn(background: string): string {
  const bg = normalizeHex(background);
  const dark = '#0f172a';
  const light = '#ffffff';

  return contrastRatio(dark, bg) >= contrastRatio(light, bg) ? dark : light;
}

function mutedFor(background: string): string {
  const bg = normalizeHex(background);
  const lightBackgroundMuted = '#475569';
  const darkBackgroundMuted = '#cbd5e1';

  if (contrastRatio(lightBackgroundMuted, bg) >= 4.5) {
    return lightBackgroundMuted;
  }

  if (contrastRatio(darkBackgroundMuted, bg) >= 4.5) {
    return darkBackgroundMuted;
  }

  return readableOn(bg);
}

function contrastRatio(foreground: string, background: string): number {
  const fg = relativeLuminance(foreground);
  const bg = relativeLuminance(background);
  const lighter = Math.max(fg, bg);
  const darker = Math.min(fg, bg);

  return (lighter + 0.05) / (darker + 0.05);
}

function relativeLuminance(hex: string): number {
  const channels = hexToRgb(hex)
    .split(',')
    .map((channel) => Number(channel) / 255)
    .map((channel) => (channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4));

  return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
}

function normalizeHex(value: string): string {
  const trimmed = value.trim();

  if (/^#[0-9a-f]{3}$/i.test(trimmed) || /^#[0-9a-f]{6}$/i.test(trimmed)) {
    return trimmed;
  }

  return '#0f172a';
}
