export const ROUND_LABELS = {
  group_stage: 'Fase de grupos',
  round_of_32: '16 avos de final',
  round_of_16: 'Oitavas de final',
  quarterfinal: 'Quartas de final',
  semifinal: 'Semifinal',
  third_place: 'Disputa de terceiro lugar',
  final: 'Final',
  unknown_stage: 'Fase não identificada',
};

export const ROUND_ALIASES = {
  gruppenphase: 'group_stage',
  vorrunde: 'group_stage',
  group_stage: 'group_stage',
  groupstage: 'group_stage',
  round_of_32: 'round_of_32',
  roundof32: 'round_of_32',
  fase_de_32: 'round_of_32',
  fase_de_16_avos: 'round_of_32',
  dezesseis_avos_de_final: 'round_of_32',
  '16_avos_de_final': 'round_of_32',
  sechzehntelfinale: 'round_of_32',
  round_of_16: 'round_of_16',
  roundof16: 'round_of_16',
  oitavas: 'round_of_16',
  oitavas_de_final: 'round_of_16',
  achtelfinale: 'round_of_16',
  quarterfinal: 'quarterfinal',
  quarterfinals: 'quarterfinal',
  quartas: 'quarterfinal',
  quartas_de_final: 'quarterfinal',
  viertelfinale: 'quarterfinal',
  semifinal: 'semifinal',
  semifinals: 'semifinal',
  semifinal_1: 'semifinal',
  semifinais: 'semifinal',
  halbfinale: 'semifinal',
  third_place: 'third_place',
  thirdplace: 'third_place',
  disputa_de_terceiro_lugar: 'third_place',
  terceiro_lugar: 'third_place',
  jogo_de_terceiro_lugar: 'third_place',
  spiel_um_platz_3: 'third_place',
  final: 'final',
  finale: 'final',
};

export const MATCH_STATE_LABELS = {
  awaiting_teams: 'Aguardando seleções',
  scheduled: 'Agendada',
  open_for_prediction: 'Palpite aberto',
  prediction_saved: 'Palpite salvo',
  locked: 'Bloqueada',
  live: 'Ao vivo',
  in_progress_unconfirmed: 'Em andamento',
  finished: 'Finalizada',
  postponed: 'Adiada',
  cancelled: 'Cancelada',
  data_delayed: 'Dados atrasados',
  unknown: 'Status desconhecido',
};

export const MATCH_STATES = Object.keys(MATCH_STATE_LABELS);

export const STAGE_ORDER = [
  'round_of_32',
  'round_of_16',
  'quarterfinal',
  'semifinal',
  'third_place',
  'final',
];

export const GERMAN_ROUND_PATTERNS = [
  'Achtel',
  'Sechzehntel',
  'Viertel',
  'Halbfinale',
  'Finale',
  'Spiel um Platz',
];

const TEAM_NAMES = {
  africa_do_sul: 'África do Sul',
  alemanha: 'Alemanha',
  argelia: 'Argélia',
  argentina: 'Argentina',
  arabia_saudita: 'Arábia Saudita',
  australia: 'Austrália',
  austria: 'Áustria',
  belgica: 'Bélgica',
  bosnia_e_herzegovina: 'Bósnia e Herzegovina',
  brasil: 'Brasil',
  cabo_verde: 'Cabo Verde',
  canada: 'Canadá',
  catar: 'Catar',
  colombia: 'Colômbia',
  coreia_do_sul: 'Coreia do Sul',
  costa_do_marfim: 'Costa do Marfim',
  croacia: 'Croácia',
  curacao: 'Curaçao',
  dinamarca: 'Dinamarca',
  egito: 'Egito',
  equador: 'Equador',
  escocia: 'Escócia',
  espanha: 'Espanha',
  estados_unidos: 'Estados Unidos',
  franca: 'França',
  gana: 'Gana',
  haiti: 'Haiti',
  inglaterra: 'Inglaterra',
  ira: 'Irã',
  iraque: 'Iraque',
  japao: 'Japão',
  jordania: 'Jordânia',
  marrocos: 'Marrocos',
  mexico: 'México',
  noruega: 'Noruega',
  nova_zelandia: 'Nova Zelândia',
  paises_baixos: 'Países Baixos',
  panama: 'Panamá',
  pais_de_gales: 'Pais de Gales',
  paraguai: 'Paraguai',
  portugal: 'Portugal',
  rd_congo: 'RD Congo',
  republica_tcheca: 'República Tcheca',
  senegal: 'Senegal',
  suecia: 'Suécia',
  suica: 'Suíça',
  tunisia: 'Tunísia',
  turquia: 'Turquia',
  uruguai: 'Uruguai',
  uzbequistao: 'Uzbequistão',
};

const TEAM_CODES = {
  africa_do_sul: 'RSA',
  alemanha: 'ALE',
  argelia: 'ALG',
  argentina: 'ARG',
  arabia_saudita: 'KSA',
  australia: 'AUS',
  austria: 'AUT',
  belgica: 'BEL',
  bosnia_e_herzegovina: 'BIH',
  brasil: 'BRA',
  cabo_verde: 'CPV',
  canada: 'CAN',
  catar: 'CAT',
  colombia: 'COL',
  coreia_do_sul: 'COR',
  costa_do_marfim: 'CIV',
  croacia: 'CRO',
  curacao: 'CUW',
  dinamarca: 'DEN',
  egito: 'EGI',
  equador: 'ECU',
  escocia: 'ESC',
  espanha: 'ESP',
  estados_unidos: 'EUA',
  franca: 'FRA',
  gana: 'GAN',
  haiti: 'HAI',
  inglaterra: 'ING',
  ira: 'IRA',
  iraque: 'IRQ',
  japao: 'JAP',
  jordania: 'JOR',
  marrocos: 'MAR',
  mexico: 'MEX',
  noruega: 'NOR',
  nova_zelandia: 'NZL',
  paises_baixos: 'HOL',
  pais_de_gales: 'WAL',
  panama: 'PAN',
  paraguai: 'PAR',
  portugal: 'POR',
  rd_congo: 'RDC',
  republica_tcheca: 'RTC',
  senegal: 'SEN',
  suecia: 'SUE',
  suica: 'SUI',
  tunisia: 'TUN',
  turquia: 'TUR',
  uruguai: 'URU',
  uzbequistao: 'UZB',
};

const TEAM_NAME_OVERRIDES = {
  africa_do_sul: 'África do Sul',
  argelia: 'Argélia',
  arabia_saudita: 'Arábia Saudita',
  australia: 'Austrália',
  austria: 'Áustria',
  belgica: 'Bélgica',
  bosnia_e_herzegovina: 'Bósnia e Herzegovina',
  canada: 'Canadá',
  colombia: 'Colômbia',
  croacia: 'Croácia',
  curacao: 'Curaçao',
  escocia: 'Escócia',
  franca: 'França',
  ira: 'Irã',
  japao: 'Japão',
  jordania: 'Jordânia',
  mexico: 'México',
  nova_zelandia: 'Nova Zelândia',
  paises_baixos: 'Países Baixos',
  panama: 'Panamá',
  pais_de_gales: 'País de Gales',
  republica_tcheca: 'República Tcheca',
  suecia: 'Suécia',
  suica: 'Suíça',
  tunisia: 'Tunísia',
  uzbequistao: 'Uzbequistão',
};

const PROVIDER_NAME_ALIASES = {
  algeria: 'argelia',
  algerien: 'argelia',
  australien: 'australia',
  belgien: 'belgica',
  bosnien_und_herzegowina: 'bosnia_e_herzegovina',
  brazil: 'brasil',
  brasilien: 'brasil',
  canada: 'canada',
  kanada: 'canada',
  czech_republic: 'republica_tcheca',
  tschechien: 'republica_tcheca',
  denmark: 'dinamarca',
  deutschland: 'alemanha',
  england: 'inglaterra',
  frankreich: 'franca',
  france: 'franca',
  germany: 'alemanha',
  ivory_coast: 'costa_do_marfim',
  elfenbeinkuste: 'costa_do_marfim',
  japan: 'japao',
  korea_republic: 'coreia_do_sul',
  sudkorea: 'coreia_do_sul',
  netherlands: 'paises_baixos',
  niederlande: 'paises_baixos',
  norway: 'noruega',
  norwegen: 'noruega',
  south_africa: 'africa_do_sul',
  sudafrika: 'africa_do_sul',
  spain: 'espanha',
  spanien: 'espanha',
  sweden: 'suecia',
  schweden: 'suecia',
  switzerland: 'suica',
  schweiz: 'suica',
  turkey: 'turquia',
  turkei: 'turquia',
  united_states: 'estados_unidos',
  usa: 'estados_unidos',
  wales: 'pais_de_gales',
};

export function normalizeContractKey(value) {
  return String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim()
    .replace(/&/g, ' e ')
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '');
}

export function normalizeRoundKey(value) {
  const key = normalizeContractKey(value);

  return ROUND_LABELS[key] ? key : (ROUND_ALIASES[key] ?? null);
}

export function roundLabelFor(value, fallback = 'Mata-mata') {
  const key = normalizeRoundKey(value);

  return key ? ROUND_LABELS[key] : fallback;
}

export function normalizeMatchState(value) {
  const key = normalizeContractKey(value);

  return MATCH_STATE_LABELS[key] ? key : 'unknown';
}

export function matchStateLabelFor(value, fallback) {
  const key = normalizeMatchState(value);

  return fallback || MATCH_STATE_LABELS[key];
}

export function localizedTeamName(value) {
  const key = normalizeContractKey(value);
  const canonicalKey = PROVIDER_NAME_ALIASES[key] ?? key;

  return TEAM_NAME_OVERRIDES[canonicalKey] ?? TEAM_NAMES[canonicalKey] ?? value;
}

export function teamCodeFor(team, label) {
  const explicitCode = [
    team?.code,
    team?.country_code,
    team?.iso_code,
    team?.abbreviation,
  ].find((candidate) => /^[A-Za-z]{2,4}$/.test(String(candidate ?? '').trim()));

  if (explicitCode) {
    return explicitCode.toUpperCase();
  }

  const key = normalizeContractKey(label);
  const canonicalKey = PROVIDER_NAME_ALIASES[key] ?? key;

  return TEAM_CODES[canonicalKey] ?? '???';
}
