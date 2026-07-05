export function BrandLogo({
  href = '/brand/copa-zone-logo.png',
  alt = 'CopaZone',
  size = 40,
  wordmark = true,
  subtitle = 'Palpites da Copa',
  className = '',
}) {
  const classes = ['brand-logo', className].filter(Boolean).join(' ');

  return (
    <span className={classes}>
      <img src={href} alt={alt} width={size} height={size} className="brand-logo-image" />
      {wordmark ? (
        <span className="brand-logo-copy">
          <strong>CopaZone</strong>
          <small>{subtitle}</small>
        </span>
      ) : null}
    </span>
  );
}
