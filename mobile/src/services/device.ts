import { Capacitor } from '@capacitor/core';
import { Device } from '@capacitor/device';

export const DEFAULT_DEVICE_NAME = 'Amref training DB mobile app';

export async function getAutomaticDeviceName(): Promise<string> {
  try {
    const info = await Device.getInfo();
    const reportedName = cleanPart(info.name);
    const modelName = cleanPart(formatModelName(info.manufacturer, info.model));
    const primaryName = uniqueParts([reportedName, modelName]).join(' - ');
    const platformName = cleanPart(formatPlatformName(info.operatingSystem, info.osVersion));
    const deviceName = uniqueParts([primaryName, platformName]).join(' - ');

    return limitDeviceName(deviceName || fallbackDeviceName());
  } catch {
    return fallbackDeviceName();
  }
}

function fallbackDeviceName(): string {
  const navigatorWithUserAgentData = navigator as Navigator & {
    userAgentData?: {
      platform?: string;
    };
  };
  const platform = cleanPart(navigatorWithUserAgentData.userAgentData?.platform || navigator.platform);

  if (platform) {
    return limitDeviceName(`${DEFAULT_DEVICE_NAME} - ${platform}`);
  }

  const appPlatform = Capacitor.getPlatform();

  return appPlatform && appPlatform !== 'web'
    ? limitDeviceName(`${DEFAULT_DEVICE_NAME} - ${appPlatform}`)
    : DEFAULT_DEVICE_NAME;
}

function formatModelName(manufacturer: string | undefined, model: string | undefined): string {
  const manufacturerName = cleanPart(manufacturer);
  const modelName = cleanPart(model);

  if (!manufacturerName) {
    return modelName;
  }

  if (!modelName || modelName.toLowerCase().startsWith(manufacturerName.toLowerCase())) {
    return modelName || manufacturerName;
  }

  return `${manufacturerName} ${modelName}`;
}

function formatPlatformName(operatingSystem: string | undefined, osVersion: string | undefined): string {
  const osName = cleanPart(operatingSystem);
  const version = cleanPart(osVersion);

  return uniqueParts([osName, version]).join(' ');
}

function uniqueParts(parts: string[]): string[] {
  const seen = new Set<string>();

  return parts.filter((part) => {
    const normalized = part.toLowerCase();

    if (!normalized || seen.has(normalized)) {
      return false;
    }

    seen.add(normalized);

    return true;
  });
}

function cleanPart(value: string | null | undefined): string {
  return String(value || '')
    .replace(/\s+/g, ' ')
    .trim();
}

function limitDeviceName(value: string): string {
  return value.length > 120 ? value.slice(0, 120).trim() : value;
}
