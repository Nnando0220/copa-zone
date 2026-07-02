const PERIODS = [
  { value: 'all', label: 'Todas' },
  { value: 'current', label: 'Atuais' },
  { value: 'upcoming', label: 'Proximas' },
  { value: 'today', label: 'Hoje' },
  { value: 'finished', label: 'Finalizadas' },
];

export function MatchPeriodFilter({ value, onChange, total }) {
  return (
    <div className="match-filter-bar" aria-label="Filtrar partidas">
      <div>
        <span>Filtro</span>
        <strong>{total ?? 0} partidas</strong>
      </div>

      <div className="match-filter-options">
        {PERIODS.map((period) => (
          <button
            key={period.value}
            type="button"
            className={period.value === value ? 'active' : ''}
            onClick={() => onChange(period.value)}
          >
            {period.label}
          </button>
        ))}
      </div>
    </div>
  );
}
