import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { ArrowLeft, KeyRound, Trophy } from 'lucide-react';
import { motion } from 'motion/react';
import { toast } from 'sonner';
import { portalService } from '../services/portal-service';

const CODE_SIZE = 8;

function normalizeInviteCode(value) {
  return value.replace(/[^a-z0-9]/gi, '').toUpperCase().slice(0, CODE_SIZE);
}

export function JoinLeaguePage() {
  const navigate = useNavigate();
  const inputRef = useRef(null);
  const clearAnimationTimer = useRef(null);
  const [inviteCode, setInviteCode] = useState('');
  const [clearingSlots, setClearingSlots] = useState([]);
  const [isJoining, setIsJoining] = useState(false);
  const [isPreviewing, setIsPreviewing] = useState(false);
  const [preview, setPreview] = useState(null);
  const [error, setError] = useState('');

  const normalizedCode = normalizeInviteCode(inviteCode);
  const codeSlots = Array.from({ length: CODE_SIZE }, (_, index) => normalizedCode[index] || '');
  const canFindLeague = normalizedCode.length === CODE_SIZE && !isPreviewing && !isJoining;
  const previewLeague = preview?.data?.league;
  const alreadyMember = preview?.meta?.already_member;

  useEffect(() => () => window.clearTimeout(clearAnimationTimer.current), []);

  function updateInviteCode(value) {
    const nextCode = normalizeInviteCode(value);
    const removedSlots = codeSlots
      .map((slot, index) => (slot && !nextCode[index] ? index : null))
      .filter((index) => index !== null);

    setInviteCode(nextCode);
    window.clearTimeout(clearAnimationTimer.current);

    if (removedSlots.length > 0) {
      setClearingSlots(removedSlots);
      clearAnimationTimer.current = window.setTimeout(() => setClearingSlots([]), 520);
    } else {
      setClearingSlots([]);
    }

    setPreview(null);
    setError('');
  }

  function handlePaste(event) {
    event.preventDefault();
    updateInviteCode(event.clipboardData.getData('text'));
  }

  function focusCodeInput() {
    inputRef.current?.focus();
  }

  async function previewInvite(event) {
    event.preventDefault();
    setIsPreviewing(true);
    setError('');

    try {
      const payload = await portalService.previewByCode(normalizedCode);
      setPreview(payload);

      if (payload.meta?.already_member) {
        toast.info('Você já faz parte dessa liga.');
      } else {
        toast.success('Liga encontrada.');
      }
    } catch (requestError) {
      setPreview(null);
      setError(requestError.message || 'Não encontramos uma liga disponível com esse código. Confira os caracteres e tente novamente.');
    } finally {
      setIsPreviewing(false);
    }
  }

  async function confirmEntry() {
    setIsJoining(true);
    setError('');

    try {
      const payload = await portalService.joinByCode(normalizedCode);
      toast.success('Você entrou para a torcida!');
      navigate(`/ligas/${payload.data.league.id}`);
    } catch (requestError) {
      setError(requestError.message || 'Não foi possível confirmar sua entrada nessa liga.');
    } finally {
      setIsJoining(false);
    }
  }

  return (
    <section className="join-league-page">
      <motion.div
        className="join-league-layout single"
        initial={{ opacity: 0, y: 12 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.28 }}
      >
        <form className="league-form-panel join-league-form quick-entry-only" onSubmit={previewInvite}>
          <div className="join-code-header">
            <KeyRound size={22} />
            <div>
              <p className="eyebrow">Entrada rápida</p>
              <h3>Entre na torcida da sua liga</h3>
              <span>Digite o código de 8 caracteres enviado pelo gestor.</span>
            </div>
          </div>

          <Link className="join-league-back" to="/ligas/minhas">
            <ArrowLeft size={17} />
            Voltar para Minhas Ligas
          </Link>

          <label className="supporters-code-entry" onClick={focusCodeInput}>
            <span className="sr-only">Código da liga</span>
            <input
              ref={inputRef}
              className="supporters-code-input"
              value={normalizedCode}
              onChange={(event) => updateInviteCode(event.target.value)}
              onPaste={handlePaste}
              aria-describedby={error ? 'invite-code-error' : 'invite-code-help'}
              inputMode="text"
              autoComplete="off"
              maxLength={CODE_SIZE}
              autoCapitalize="characters"
            />
            <span className="supporters-stand" aria-hidden="true">
              <span className="stadium-tier top" />
              <span className="stadium-tier middle" />
              <span className="stadium-tier bottom" />
              {codeSlots.map((slot, index) => {
                const className = [
                  'supporter-card',
                  slot ? 'filled' : '',
                  clearingSlots.includes(index) ? 'clearing' : '',
                ].filter(Boolean).join(' ');

                return (
                  <span key={index} className={className}>
                    <span className="supporter-head" />
                    <span className="supporter-body" />
                    <span className="supporter-poster">{slot || '-'}</span>
                  </span>
                );
              })}
            </span>
          </label>

          <p id="invite-code-help" className="join-code-help">
            Você pode digitar ou colar o código completo. Espaços e hifens são removidos automaticamente.
          </p>

          {isPreviewing && (
            <div className="stadium-searching" role="status">
              <span />
              Procurando sua liga...
            </div>
          )}

          {error && <div id="invite-code-error" className="content-error compact">{error}</div>}

          <button type="submit" className="primary-action" disabled={!canFindLeague}>
            {isPreviewing ? 'Procurando...' : 'Encontrar liga'}
          </button>

          <Link className="join-league-secondary" to="/ligas/publicas">
            Não tenho um código
          </Link>

          {previewLeague ? (
            <motion.div
              className="league-preview-card"
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.22 }}
            >
              <div className="join-code-header">
                <Trophy size={22} />
                <div>
                  <p className="eyebrow">Liga encontrada</p>
                  <h3>{previewLeague.name}</h3>
                </div>
              </div>

              <div className="league-preview-list">
                <span>
                  <small>Modo</small>
                  <strong>Copa do Mundo</strong>
                </span>
                <span>
                  <small>Gestor</small>
                  <strong>{previewLeague.owner_name || 'Gestor da liga'}</strong>
                </span>
                <span>
                  <small>Participantes</small>
                  <strong>
                    {previewLeague.members_count ?? 0}/{previewLeague.max_members}
                  </strong>
                </span>
                <span>
                  <small>Status</small>
                  <strong>{previewLeague.status === 'open' ? 'Aberta' : previewLeague.status}</strong>
                </span>
              </div>

              {alreadyMember ? (
                <>
                  <div className="content-error compact neutral">Você já faz parte dessa liga.</div>
                  <button type="button" className="primary-action" onClick={() => navigate(`/ligas/${previewLeague.id}`)}>
                    Acessar liga
                  </button>
                </>
              ) : (
                <button type="button" className="primary-action" onClick={confirmEntry} disabled={isJoining}>
                  {isJoining ? 'Confirmando entrada...' : 'Confirmar entrada'}
                </button>
              )}
            </motion.div>
          ) : null}
        </form>
      </motion.div>
    </section>
  );
}
