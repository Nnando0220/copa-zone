import { useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { ArrowLeft, CheckCircle2, Globe2, LockKeyhole, Minus, Plus, Users } from 'lucide-react';
import { motion } from 'motion/react';
import { toast } from 'sonner';
import { portalService } from '../services/portal-service';

const MIN_MEMBERS = 2;
const MAX_MEMBERS = 32;
const NAME_MAX = 120;
const suggestedLimits = [8, 16, 24, 32];

const initialLeagueForm = {
  name: '',
  visibility: 'public',
  max_members: 32,
};

const creationPhases = [
  {
    id: 'name',
    eyebrow: 'Faixa principal',
    title: 'Escolha o nome da liga',
    copy: 'Esse sera o letreiro que todos os participantes vao reconhecer.',
    artClass: 'name',
  },
  {
    id: 'visibility',
    eyebrow: 'Portoes do estadio',
    title: 'Defina como a torcida entra',
    copy: 'Escolha se a liga aparece publicamente ou se a entrada sera por convite.',
    artClass: 'visibility',
  },
  {
    id: 'capacity',
    eyebrow: 'Arquibancada',
    title: 'Defina a capacidade',
    copy: 'Use o controle da arquibancada para escolher ate 32 lugares para a liga.',
    artClass: 'capacity',
  },
  {
    id: 'confirm',
    eyebrow: 'Apito inicial',
    title: 'Revise o estadio',
    copy: 'Confira os dados essenciais antes de abrir os portoes da sua liga.',
    artClass: 'confirm',
  },
];

function normalizeLeagueName(value) {
  return value.replace(/\s+/g, ' ').trim();
}

function clampCapacity(value) {
  const numericValue = Number.parseInt(value, 10);

  if (Number.isNaN(numericValue)) {
    return MIN_MEMBERS;
  }

  return Math.min(MAX_MEMBERS, Math.max(MIN_MEMBERS, numericValue));
}

function visibilityLabel(visibility) {
  return visibility === 'private' ? 'Entrada com convite' : 'Portoes abertos';
}

export function CreateLeaguePage() {
  const navigate = useNavigate();
  const nameRef = useRef(null);
  const capacityRef = useRef(null);
  const [activePhaseIndex, setActivePhaseIndex] = useState(0);
  const [form, setForm] = useState(initialLeagueForm);
  const [fieldErrors, setFieldErrors] = useState({});
  const [isCreating, setIsCreating] = useState(false);
  const [createdLeague, setCreatedLeague] = useState(null);
  const [error, setError] = useState('');

  const activePhase = creationPhases[activePhaseIndex];
  const isConfirmPhase = activePhase.id === 'confirm';
  const previewName = normalizeLeagueName(form.name) || 'Nome da sua liga';

  function updateField(event) {
    const { name, value } = event.target;

    setCreatedLeague(null);
    setFieldErrors((current) => ({ ...current, [name]: '' }));
    setForm((current) => ({
      ...current,
      [name]: name === 'max_members' ? clampCapacity(value) : value,
    }));
  }

  function setCapacity(value) {
    setCreatedLeague(null);
    setFieldErrors((current) => ({ ...current, max_members: '' }));
    setForm((current) => ({ ...current, max_members: clampCapacity(value) }));
  }

  function changeCapacity(delta) {
    setCapacity(form.max_members + delta);
  }

  function validatePhase(phaseId = activePhase.id) {
    const nextErrors = {};
    const normalizedName = normalizeLeagueName(form.name);

    if ((phaseId === 'name' || phaseId === 'confirm') && normalizedName.length < 3) {
      nextErrors.name = 'Informe um nome com pelo menos 3 caracteres.';
    }

    if ((phaseId === 'name' || phaseId === 'confirm') && normalizedName.length > NAME_MAX) {
      nextErrors.name = `O nome deve ter no maximo ${NAME_MAX} caracteres.`;
    }

    if ((phaseId === 'visibility' || phaseId === 'confirm') && !['public', 'private'].includes(form.visibility)) {
      nextErrors.visibility = 'Escolha se a liga sera publica ou privada.';
    }

    if ((phaseId === 'capacity' || phaseId === 'confirm') && (form.max_members < MIN_MEMBERS || form.max_members > MAX_MEMBERS)) {
      nextErrors.max_members = `Use um limite entre ${MIN_MEMBERS} e ${MAX_MEMBERS} participantes.`;
    }

    setFieldErrors(nextErrors);

    if (nextErrors.name) {
      setActivePhaseIndex(0);
      requestAnimationFrame(() => nameRef.current?.focus());
    } else if (nextErrors.visibility) {
      setActivePhaseIndex(1);
    } else if (nextErrors.max_members) {
      setActivePhaseIndex(2);
      requestAnimationFrame(() => capacityRef.current?.focus());
    }

    return Object.keys(nextErrors).length === 0;
  }

  function goToPhase(index) {
    if (index <= activePhaseIndex || validatePhase()) {
      setActivePhaseIndex(index);
      setError('');
    }
  }

  function nextPhase() {
    if (!validatePhase()) {
      return;
    }

    setError('');
    setActivePhaseIndex((current) => Math.min(current + 1, creationPhases.length - 1));
  }

  function previousPhase() {
    setError('');
    setActivePhaseIndex((current) => Math.max(current - 1, 0));
  }

  async function submitLeague() {
    if (!validatePhase('confirm')) {
      return;
    }

    setIsCreating(true);
    setError('');
    setCreatedLeague(null);

    try {
      const payload = await portalService.createLeague({
        name: normalizeLeagueName(form.name),
        visibility: form.visibility,
        max_members: form.max_members,
      });
      const league = payload.data.league;

      setCreatedLeague(league);
      setForm(initialLeagueForm);
      toast.success('Seu estadio esta pronto!');
      navigate(`/ligas/${league.id}/copa`);
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel criar a liga.');
    } finally {
      setIsCreating(false);
    }
  }

  function handleSubmit(event) {
    event.preventDefault();

    if (isConfirmPhase) {
      submitLeague();
      return;
    }

    nextPhase();
  }

  function renderPhaseScene() {
    if (activePhase.id === 'name') {
      return (
        <div className="phase-scene-layer">
          <div className="stadium-marquee-input">
            <label htmlFor="league-name">Nome da liga</label>
            <input
              ref={nameRef}
              id="league-name"
              name="name"
              autoComplete="off"
              data-lpignore="true"
              data-form-type="other"
              value={form.name}
              onChange={updateField}
              onBlur={() => setForm((current) => ({ ...current, name: normalizeLeagueName(current.name) }))}
              minLength={3}
              maxLength={NAME_MAX}
              placeholder="Liga dos Amigos"
              aria-invalid={Boolean(fieldErrors.name)}
              aria-describedby={fieldErrors.name ? 'league-name-error' : 'league-name-help'}
              required
            />
            <span className="marquee-count">{normalizeLeagueName(form.name).length}/{NAME_MAX}</span>
            <p id="league-name-help" className="field-help">Esse nome aparecera para todos os participantes.</p>
            {fieldErrors.name && <p id="league-name-error" className="field-error">{fieldErrors.name}</p>}
          </div>
        </div>
      );
    }

    if (activePhase.id === 'visibility') {
      return (
        <div className="phase-scene-layer">
          <fieldset className="stadium-gate-selector">
            <legend>Tipo de acesso</legend>
            <label className={form.visibility === 'public' ? 'selected' : undefined}>
              <input
                type="radio"
                name="visibility"
                value="public"
                checked={form.visibility === 'public'}
                onChange={updateField}
              />
              <span className="gate-doors open" aria-hidden="true">
                <span />
                <span />
              </span>
              {form.visibility === 'public' && (
                <span className="gate-selected-badge" aria-hidden="true">
                  <CheckCircle2 size={15} />
                </span>
              )}
              <span className="gate-choice-copy">
                <Globe2 size={20} />
                <strong>Portoes abertos</strong>
                <span className="gate-choice-desc">Qualquer torcedor pode encontrar e entrar na liga livremente.</span>
              </span>
            </label>
            <label className={form.visibility === 'private' ? 'selected' : undefined}>
              <input
                type="radio"
                name="visibility"
                value="private"
                checked={form.visibility === 'private'}
                onChange={updateField}
              />
              <span className="gate-doors closed" aria-hidden="true">
                <span />
                <span />
              </span>
              {form.visibility === 'private' && (
                <span className="gate-selected-badge" aria-hidden="true">
                  <CheckCircle2 size={15} />
                </span>
              )}
              <span className="gate-choice-copy">
                <LockKeyhole size={20} />
                <strong>Entrada com convite</strong>
                <span className="gate-choice-desc">Somente quem receber o codigo de convite podera participar.</span>
              </span>
            </label>
            {fieldErrors.visibility && <p className="field-error">{fieldErrors.visibility}</p>}
          </fieldset>
          <p className="stadium-gate-caption">{visibilityLabel(form.visibility)}</p>
        </div>
      );
    }

    if (activePhase.id === 'capacity') {
      const filledSeats = Math.round((form.max_members / MAX_MEMBERS) * MAX_MEMBERS);

      return (
        <div className="phase-scene-layer">
          <div className="stadium-seat-selector">
            <div className="seat-selector-score">
              <label htmlFor="league-capacity">Lugares da liga</label>
              <strong>{form.max_members}</strong>
              <span>de {MAX_MEMBERS}</span>
            </div>
            <div className="seat-selector-map" aria-hidden="true">
              {Array.from({ length: MAX_MEMBERS }).map((_, index) => (
                <span key={index} className={index < filledSeats ? 'active' : undefined} />
              ))}
            </div>
            <div className="seat-selector-control">
              <button type="button" onClick={() => changeCapacity(-1)} aria-label="Diminuir limite">
                <Minus size={17} />
              </button>
              <input
                ref={capacityRef}
                id="league-capacity"
                className="capacity-slider"
                type="range"
                name="max_members"
                min={MIN_MEMBERS}
                max={MAX_MEMBERS}
                value={form.max_members}
                onChange={(event) => setCapacity(event.target.value)}
                aria-invalid={Boolean(fieldErrors.max_members)}
                aria-label="Selecionar capacidade da arquibancada"
                required
              />
              <button type="button" onClick={() => changeCapacity(1)} aria-label="Aumentar limite">
                <Plus size={17} />
              </button>
            </div>
            <div className="capacity-chips" aria-label="Limites sugeridos">
              {suggestedLimits.map((limit) => (
                <button key={limit} type="button" onClick={() => setCapacity(limit)}>
                  {limit}
                </button>
              ))}
            </div>
            {fieldErrors.max_members && <p className="field-error">{fieldErrors.max_members}</p>}
          </div>
        </div>
      );
    }

    return (
      <div className="phase-scene-layer">
        <div className="stadium-confirm-board">
          <div className="creation-review-list">
            <article>
              <span>Nome</span>
              <strong>{previewName}</strong>
            </article>
            <article>
              <span>Visibilidade</span>
              <strong>{form.visibility === 'private' ? 'Privada' : 'Publica'}</strong>
            </article>
            <article>
              <span>Limite</span>
              <strong>{form.max_members} participantes</strong>
            </article>
          </div>
          {error && <div className="content-error compact">{error}</div>}
          {createdLeague && (
            <motion.div
              className="league-created-panel"
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.24 }}
            >
              <CheckCircle2 size={22} />
              <div>
                <p className="eyebrow">Estadio pronto</p>
                <h3>{createdLeague.name} foi criada com sucesso.</h3>
                {createdLeague.invite_code && (
                  <div className="created-league-code compact">
                    <span>Codigo privado</span>
                    <strong>{createdLeague.invite_code}</strong>
                  </div>
                )}
                <div className="created-league-actions">
                  <button type="button" className="primary-action" onClick={() => navigate(`/ligas/${createdLeague.id}`)}>
                    Acessar liga
                  </button>
                  <Link className="secondary-action action-link" to="/ligas/minhas">
                    Minhas ligas
                  </Link>
                </div>
              </div>
            </motion.div>
          )}
        </div>
      </div>
    );
  }

  return (
    <section className="create-league-page stadium-create-page dynamic-create-page">
      <motion.div
        className="stadium-create-hero"
        initial={{ opacity: 0, y: 12 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.28 }}
      >
        <div>
          <p className="eyebrow">Preparar estadio</p>
          <h2>Crie sua liga fase por fase</h2>
          <p>Nome, portoes e arquibancada aparecem como cenas separadas antes do apito inicial.</p>
        </div>
        <Link className="join-league-back" to="/ligas/minhas">
          <ArrowLeft size={17} />
          Voltar
        </Link>
      </motion.div>

      <form className="dynamic-create-shell" onSubmit={handleSubmit} autoComplete="off" noValidate>
        <nav className="creation-phase-tabs" aria-label="Fases da criacao">
          {creationPhases.map((phase, index) => (
            <button
              key={phase.id}
              type="button"
              className={index === activePhaseIndex ? 'active' : undefined}
              onClick={() => goToPhase(index)}
            >
              <span>{index + 1}</span>
              {phase.eyebrow}
            </button>
          ))}
        </nav>

        <motion.div
          key={activePhase.id}
          className="dynamic-create-stage visual-only"
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.24 }}
        >
          <div className={`dynamic-phase-image embedded-form ${activePhase.artClass}`}>
            <div className="embedded-phase-heading">
              <p className="eyebrow">{activePhase.eyebrow}</p>
              <h3>{activePhase.title}</h3>
              <p>{activePhase.copy}</p>
            </div>
            {renderPhaseScene()}
          </div>
        </motion.div>

        <footer className="dynamic-create-actions">
          <button type="button" className="secondary-action" onClick={previousPhase} disabled={activePhaseIndex === 0 || isCreating}>
            Voltar fase
          </button>
          <button type="submit" className="primary-action" disabled={isCreating}>
            {isCreating ? 'Preparando o estadio...' : isConfirmPhase ? 'Dar o apito inicial' : 'Continuar'}
          </button>
        </footer>
      </form>

      <div className="invite-route-card compact">
        <Users size={20} />
        <div>
          <strong>Quer entrar em uma liga?</strong>
          <span>Use a tela de codigo quando receber um convite privado.</span>
        </div>
        <Link className="secondary-action action-link" to="/ligas/entrar">
          Colar codigo
        </Link>
      </div>
    </section>
  );
}
