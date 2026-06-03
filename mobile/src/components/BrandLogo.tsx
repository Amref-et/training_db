import { brandInitials, useAppearance } from '../services/appearance';

export default function BrandLogo({ compact = false }: { compact?: boolean }) {
  const { settings } = useAppearance();
  const logoUrl = settings.logos.header_url || settings.logos.footer_url;

  if (logoUrl) {
    return (
      <span className={compact ? 'brand-logo compact' : 'brand-logo'}>
        <img
          src={logoUrl}
          alt={`${settings.site.name} logo`}
          style={{ maxHeight: compact ? 34 : Math.min(settings.logos.header_height, 72) }}
        />
      </span>
    );
  }

  return <span className={compact ? 'brand-mark compact' : 'brand-mark'}>{brandInitials(settings.site.name)}</span>;
}
