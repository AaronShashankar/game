// Small synthesized sound effects (WebAudio, no files to download).
// Quiet by design; the mute choice is remembered on this device.
window.Sound = (function () {
  const KEY = 'mq-sound';
  let muted = false;
  try { muted = localStorage.getItem(KEY) === 'off'; } catch (e) { /* storage blocked: default on */ }
  let ctx = null;

  function audio() {
    if (!ctx) {
      const AC = window.AudioContext || window.webkitAudioContext;
      if (!AC) return null;
      ctx = new AC();
    }
    if (ctx.state === 'suspended') ctx.resume();
    return ctx;
  }

  // One soft note: frequency (Hz), start offset and length (s), waveform, peak volume.
  function tone(freq, at, len, type = 'sine', vol = 0.06) {
    const a = audio();
    if (!a) return;
    const t = a.currentTime + at;
    const osc = a.createOscillator();
    const gain = a.createGain();
    osc.type = type;
    osc.frequency.setValueAtTime(freq, t);
    gain.gain.setValueAtTime(0.0001, t);
    gain.gain.exponentialRampToValueAtTime(vol, t + 0.012);
    gain.gain.exponentialRampToValueAtTime(0.0001, t + len);
    osc.connect(gain).connect(a.destination);
    osc.start(t);
    osc.stop(t + len + 0.02);
  }

  const EFFECTS = {
    correct:  () => { tone(660, 0, 0.12); tone(880, 0.09, 0.18); },
    wrong:    () => { tone(220, 0, 0.18, 'triangle', 0.07); tone(175, 0.12, 0.22, 'triangle', 0.07); },
    pickup:   () => { tone(1046, 0, 0.08, 'sine', 0.045); tone(1318, 0.06, 0.12, 'sine', 0.045); },
    gate:     () => tone(520, 0, 0.08, 'sine', 0.035),
    complete: () => [523, 659, 784, 1046].forEach((f, i) => tone(f, i * 0.09, 0.22)),
    timeout:  () => { tone(330, 0, 0.2, 'triangle', 0.06); tone(247, 0.18, 0.3, 'triangle', 0.06); },
    power:    () => tone(740, 0, 0.12, 'sine', 0.04),
  };

  return {
    play(name) {
      if (muted || !EFFECTS[name]) return;
      try { EFFECTS[name](); } catch (e) { /* audio unavailable */ }
    },
    get muted() { return muted; },
    toggle() {
      muted = !muted;
      try { localStorage.setItem(KEY, muted ? 'off' : 'on'); } catch (e) { /* not persisted */ }
      if (!muted) this.play('gate');
      return muted;
    },
  };
})();
