import { createContext, ReactNode, useContext, useEffect, useMemo, useState } from 'react';

import {
  ApiUser,
  getStoredToken,
  login as loginRequest,
  logout as logoutRequest,
  me,
  setApiBaseUrl,
  setStoredToken,
} from './api';

type AuthState = {
  ready: boolean;
  user: ApiUser | null;
  token: string | null;
  login: (params: { email: string; password: string; deviceName: string; apiBaseUrl: string }) => Promise<void>;
  logout: () => Promise<void>;
  refresh: () => Promise<void>;
};

const AuthContext = createContext<AuthState | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [ready, setReady] = useState(false);
  const [user, setUser] = useState<ApiUser | null>(null);
  const [token, setToken] = useState<string | null>(null);

  const refresh = async () => {
    const storedToken = await getStoredToken();
    setToken(storedToken);

    if (!storedToken) {
      setUser(null);
      setReady(true);

      return;
    }

    try {
      setUser(await me(storedToken));
    } catch {
      await setStoredToken(null);
      setUser(null);
      setToken(null);
    } finally {
      setReady(true);
    }
  };

  useEffect(() => {
    void refresh();
  }, []);

  useEffect(() => {
    const handleUnauthorized = () => {
      setUser(null);
      setToken(null);
    };

    window.addEventListener('hil-mobile-unauthorized', handleUnauthorized);

    return () => window.removeEventListener('hil-mobile-unauthorized', handleUnauthorized);
  }, []);

  const value = useMemo<AuthState>(
    () => ({
      ready,
      user,
      token,
      async login({ email, password, deviceName, apiBaseUrl }) {
        await setApiBaseUrl(apiBaseUrl);
        const auth = await loginRequest(email, password, deviceName);
        setToken(auth.access_token);
        setUser(auth.user);
      },
      async logout() {
        await logoutRequest();
        setToken(null);
        setUser(null);
      },
      refresh,
    }),
    [ready, token, user]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthState {
  const value = useContext(AuthContext);

  if (!value) {
    throw new Error('useAuth must be used inside AuthProvider');
  }

  return value;
}
