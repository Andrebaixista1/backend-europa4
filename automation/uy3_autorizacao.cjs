const { chromium } = require('playwright');

(async () => {
  const [, , url, nome, cpf, telefone, dataNascimento] = process.argv;

  if (!url || !nome || !cpf || !telefone || !dataNascimento) {
    console.error('Parâmetros inválidos.');
    process.exit(1);
  }

  const browser = await chromium.launch({
    headless: true,
  });

  const page = await browser.newPage();

  try {
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });

    const preencherPrimeiroDisponivel = async (seletores, valor) => {
      for (const seletor of seletores) {
        const locator = page.locator(seletor).first();
        if (await locator.count()) {
          await locator.fill('');
          await locator.fill(valor);
          return true;
        }
      }
      return false;
    };

    const cliclarPrimeiroDisponivel = async (seletores) => {
      for (const seletor of seletores) {
        const locator = page.locator(seletor).first();
        if (await locator.count()) {
          await locator.click();
          return true;
        }
      }
      return false;
    };

    const nomeOk = await preencherPrimeiroDisponivel([
      'input[name="name"]',
      'input[name="nome"]',
      'input[id="name"]',
      'input[id="nome"]',
      'input[placeholder*="nome" i]',
      'input[type="text"]'
    ], nome);

    const cpfOk = await preencherPrimeiroDisponivel([
      'input[name="cpf"]',
      'input[id="cpf"]',
      'input[placeholder*="cpf" i]',
      'input[inputmode="numeric"]'
    ], cpf);

    const telefoneOk = await preencherPrimeiroDisponivel([
      'input[name="phone"]',
      'input[name="telefone"]',
      'input[name="celular"]',
      'input[id="phone"]',
      'input[id="telefone"]',
      'input[id="celular"]',
      'input[placeholder*="celular" i]',
      'input[placeholder*="telefone" i]'
    ], telefone);

    const dataOk = await preencherPrimeiroDisponivel([
      'input[name="birthDate"]',
      'input[name="dataNascimento"]',
      'input[name="data_nascimento"]',
      'input[id="birthDate"]',
      'input[id="dataNascimento"]',
      'input[id="data_nascimento"]',
      'input[placeholder*="nascimento" i]',
      'input[placeholder*="data" i]'
    ], dataNascimento);

    if (!nomeOk) {
      throw new Error('Campo nome não encontrado.');
    }

    if (!cpfOk) {
      throw new Error('Campo CPF não encontrado.');
    }

    if (!telefoneOk) {
      throw new Error('Campo telefone não encontrado.');
    }

    if (!dataOk) {
      throw new Error('Campo data de nascimento não encontrado.');
    }

    const checkOk = await cliclarPrimeiroDisponivel([
      'input[type="checkbox"]',
      '[role="checkbox"]',
      'label:has(input[type="checkbox"])',
      'text=/Li e aceito os termos/i',
      'text=/termos e condições/i'
    ]);

    if (!checkOk) {
      throw new Error('Checkbox de autorização não encontrado.');
    }

    const botaoOk = await cliclarPrimeiroDisponivel([
      'button:has-text("Enviar cadastro")',
      'button:has-text("ENVIAR CADASTRO")',
      'button:has-text("Enviar")',
      'input[type="submit"]',
      'button[type="submit"]'
    ]);

    if (!botaoOk) {
      throw new Error('Botão enviar não encontrado.');
    }

    await page.waitForTimeout(5000);

    console.log('AUTORIZACAO_OK');
    await browser.close();
    process.exit(0);
  } catch (error) {
    console.error(error.message);
    await browser.close();
    process.exit(1);
  }
})();