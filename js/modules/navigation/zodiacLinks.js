const ZODIAC_LINKS = {
  BERBEC: 'zodiac/berbec.html',
  TAUR: 'zodiac/taur.html',
  GEMENI: 'zodiac/gemeni.html',
  RAC: 'zodiac/rac.html',
  LEU: 'zodiac/leu.html',
  'FECIOARĂ': 'zodiac/fecioara.html',
  'BALANȚĂ': 'zodiac/balanta.html',
  SCORPION: 'zodiac/scorpion.html',
  'SĂGETĂTOR': 'zodiac/sagetator.html',
  CAPRICORN: 'zodiac/capricorn.html',
  'VĂRSĂTOR': 'zodiac/varsator.html',
  'PEȘTI': 'zodiac/pesti.html',
};

export function initZodiacNavigation() {
  const signs = document.querySelectorAll('.zodiac-sign');
  signs.forEach((sign) => {
    sign.style.cursor = 'pointer';
    sign.addEventListener('click', () => {
      const name = sign.querySelector('.zodiac-name')?.innerText.trim().toUpperCase();
      const targetPage = name ? ZODIAC_LINKS[name] : null;
      if (targetPage) {
        window.location.href = targetPage;
      } else if (name) {
        console.error(`No link found for: ${name}`);
      }
    });
  });
}
