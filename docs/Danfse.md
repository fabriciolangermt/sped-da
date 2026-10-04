# DANFSe

O **D**ocumento **A**uxiliar da **NFS-e** é a representação em PDF da Nota Fiscal de Serviço eletrônica. A classe `NFePHP\DA\NFSe\Danfse` gera localmente o layout nacional DANFSe v2.0 conforme a NT 008, sem depender da API nacional de geração de PDF.

## Class Danfse

### function __construct()

Método construtor. Recebe o XML da NFS-e nacional.

```php
$danfse = new Danfse($xml);
```

### function render()

Renderiza o PDF e retorna o conteúdo binário.

```php
$pdf = $danfse->render();
```

Opcionalmente recebe uma logomarca. Quando nenhuma logomarca é informada, a classe usa a logomarca nacional da NFS-e convertida para JPEG em `src/NFSe/assets/logo-nfse.jpg`.

```php
$pdf = $danfse->render($logo);
```

## Métodos opcionais

### function setPrintCanhoto()

Controla a impressão do canhoto, que é opcional na NT 008.

```php
$danfse->setPrintCanhoto(false);
```

### function setPrintCanhotoCutLine()

Imprime uma linha pontilhada de destaque acima do canhoto. O padrão é `false`, pois a NT 008 não exige essa guia.

```php
$danfse->setPrintCanhotoCutLine(true);
```

### function setPrintBackgrounds()

Controla os fundos cinza claros. O padrão é `true`, conforme a NT 008 para cabeçalho, títulos de blocos, emitente e valor líquido com IBS/CBS.

```php
$danfse->setPrintBackgrounds(false);
```

Use `false` apenas quando houver uma necessidade operacional de impressão sem sombreamento. O PDF padrão conforme NT 008 deve manter os fundos cinza.

### function setAsCanceled()

Força a marca d'água `CANCELADA`, além da detecção automática por `cStat`.

```php
$danfse->setAsCanceled();
```

### function setAsSubstituted()

Força a marca d'água `SUBSTITUÍDA`, além da detecção automática por `cStat`.

```php
$danfse->setAsSubstituted();
```

### function creditsIntegratorFooter()

Imprime, opcionalmente, a data/hora de impressão e os dados do integrador no rodapé do DANFSe,
seguindo o mesmo padrão usado pelos demais documentos auxiliares da lib. O rodapé não é impresso
por padrão para manter o layout oficial da NT 008.

```php
$danfse->creditsIntegratorFooter('Minha software house', false);
```

## Observações de layout

- O PDF é gerado em A4 retrato, página única, com QR Code de consulta pública nacional.
- Campos ausentes no XML são impressos com traço.
- A linha de Tributação Federal é omitida para competências após 2026.
- O bloco IBS/CBS prioriza os valores consolidados de `NFSe/infNFSe/IBSCBS` e usa `infDPS/IBSCBS` como fallback para dados informados apenas na DPS.
- O motor PDF legado deste pacote suporta fontes base como Arial/Helvetica/Times. A NT 008 cita Microsoft Sans Serif para conteúdo; por ser uma fonte proprietária e não distribuída neste pacote, o conteúdo usa a fonte compatível suportada pelo motor legado, preservando tamanhos mínimos e hierarquia visual. Para conformidade estrita de fonte, a aplicação integradora deve fornecer uma fonte licenciada e um mecanismo de registro compatível com o FPDF legado.
