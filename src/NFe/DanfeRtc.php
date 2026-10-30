<?php

namespace NFePHP\DA\NFe;

/**
 * DANFE do leiaute da Reforma Tributária do Consumo (NT 2026.010), retrato.
 *
 * Reaproveita canhoto, chave, destinatário, fatura e transporte da Danfe.
 * Redesenha o cabeçalho fiscal, os totais e a grade de itens para IBS, CBS e IS.
 * Tag ausente fica em branco: nenhum valor é inventado.
 */
class DanfeRtc extends Danfe
{
    /**
     * Posições medidas na primeira passada do monta(); null na passada definitiva.
     *
     * @var array<string, float>|null
     */
    protected $medidaRtc;

    /**
     * Altura máxima dos dados adicionais para caber ao menos um item na folha 1.
     *
     * @var float|null
     */
    protected $hDadosAdicMax;

    /**
     * @param string $xml
     */
    public function __construct($xml)
    {
        parent::__construct($xml);
        $this->hCabecalho = 54;
        $this->hCabecalhoItens = 12;
        $this->hImposto = empty($this->dom) ? 18 : $this->alturaImpostoRtc();
    }

    /**
     * Sempre retrato, leiaute principal da NT: a grade de itens não comporta o paisagem (tpImp=2).
     *
     * Monta duas vezes: a primeira mede onde a grade começa de verdade, e a segunda corrige com essa
     * medida a estimativa de espaço do Danfe::monta(), que deixava a folha 1 sem item quando a nota
     * tem local de entrega e informações complementares longas.
     *
     * @param string $logo
     * @return string
     */
    protected function monta($logo = '')
    {
        $this->orientacao = 'P';
        $this->medidaRtc = [];
        $this->qtdeItensProc = 0;
        parent::monta($logo);

        $this->ajustaEspacoItens();
        $this->medidaRtc = null;
        $this->qtdeItensProc = 0;

        return parent::monta($logo);
    }

    /**
     * Acerta hCabecalho e hImposto, usados só na estimativa do Danfe::monta(), para que a área de
     * itens vá até os dados adicionais na folha 1 e até o rodapé nas seguintes.
     *
     * @return void
     */
    protected function ajustaEspacoItens()
    {
        $m = $this->medidaRtc;
        if (!isset($m['yItens'], $m['hDispo1'], $m['yHeaderIni'], $m['yHeaderFim'])) {
            return;
        }
        $hIssqn = (isset($this->ISSQNtot) && $this->getTagValue($this->ISSQNtot, 'vServ') > 0) ? 11 : 0;
        $topoDadosAdic = $this->maxH - 7 - $this->hdadosadic;
        $real1 = $topoDadosAdic - 4 - $hIssqn - 1 - $m['yItens'];
        $yItens2 = $this->margsup + ($m['yHeaderFim'] - $m['yHeaderIni']) + 1;
        $real2 = $this->maxH - 5 - 1 - $yItens2;

        $estimado2 = $this->hPrint - ($this->hCabecalho + 5 + $this->hCabecalhoItens);
        $difCabecalho = $estimado2 - $real2;
        $this->hCabecalho += $difCabecalho;
        $this->hImposto += ($m['hDispo1'] - $difCabecalho) - $real1;

        if ($this->det->length > 0) {
            $falta = $this->hCabecalhoItens + $this->calculeHeight($this->det->item(0)) - $real1;
            if ($falta > 0) {
                $this->hDadosAdicMax = $this->hdadosadic - $falta;
            }
        }
    }

    /**
     * A grade da NT não imprime o bloco de veículo novo, então ele não conta na altura do item.
     *
     * @return float
     */
    protected function alturaVeiculoNovo()
    {
        return 0;
    }

    /**
     * Reduz a fonte das informações complementares quando elas não deixam caber um item na folha 1.
     *
     * @return float
     */
    protected function calculoEspacoVericalDadosAdicionais()
    {
        $h = parent::calculoEspacoVericalDadosAdicionais();
        if (empty($this->hDadosAdicMax) || $h <= $this->hDadosAdicMax) {
            return $h;
        }
        for ($f = (int) round($this->textadicfontsize * $this->pdf->k) - 1; $f >= 5; $f--) {
            $fonte = ['font' => $this->fontePadrao, 'size' => $f, 'style' => ''];
            $this->pdf->setFont($this->fontePadrao, '', $f);
            $this->textadicfontsize = $this->pdf->fontSize;
            $h = ceil(($this->pdf->getNumLines($this->textoAdic, $this->wAdic, $fonte) + 3) * $this->textadicfontsize);
            if ($h <= $this->hDadosAdicMax) {
                break;
            }
        }

        return max($h, 10);
    }

    /**
     * Inclui CRT e reserva o quadro de regime de apuração do IBS/CBS.
     *
     * @param float $x
     * @param float $y
     * @param string $pag
     * @param string $totPag
     * @return float
     */
    protected function header($x = 0, $y = 0, $pag = '1', $totPag = '1')
    {
        if (isset($this->medidaRtc) && $pag == 1) {
            $this->medidaRtc['yHeaderIni'] = $y;
        }
        $y = parent::header($x, $y, $pag, $totPag);
        $maxW = $this->larguraUtil($pag);
        $h = 7;
        $wCrt = 62;
        $crt = $this->getTagValue($this->emit, 'CRT');
        $this->caixa($x, $y, $wCrt, $h, 'CÓDIGO DE REGIME TRIBUTÁRIO', $this->rotuloCrt($crt), 8);
        $this->caixa(
            $x + $wCrt,
            $y,
            $maxW - $wCrt,
            $h,
            'TIPO DE REGIME DE APURAÇÃO DO IBS E DA CBS',
            '',
            8
        );
        if (isset($this->medidaRtc) && $pag == 1) {
            $this->medidaRtc['yHeaderFim'] = $y + $h;
        }

        return $y + $h;
    }

    /**
     * Totais em três blocos: produtos/nota, ICMS/IPI e IBS/CBS/IS.
     *
     * @param float $x
     * @param float $y
     * @return float
     */
    protected function imposto($x, $y)
    {
        $w = $this->larguraUtil(1);
        $y = $this->tituloBloco($x, $y, $w, 'TOTAL DOS PRODUTOS E DA NOTA');
        $y = $this->linhaTotais($x, $y, $w, [
            ['PRODUTOS', $this->textoNumero($this->ICMSTot, 'vProd')],
            ['FRETE', $this->textoNumero($this->ICMSTot, 'vFrete')],
            ['SEGURO', $this->textoNumero($this->ICMSTot, 'vSeg')],
            ['DESCONTO', $this->textoNumero($this->ICMSTot, 'vDesc')],
            ['OUTRAS DESPESAS', $this->textoNumero($this->ICMSTot, 'vOutro')],
            ['TOTAL DA NOTA', $this->textoNumero($this->ICMSTot, 'vNF')],
        ]);

        $y = $this->tituloBloco($x, $y, $w, 'TOTAL DO ICMS / IPI');
        $y = $this->linhaTotais($x, $y, $w, [
            ['BASE DE CÁLC. DO ICMS', $this->textoNumero($this->ICMSTot, 'vBC')],
            ['VALOR DO ICMS', $this->textoNumero($this->ICMSTot, 'vICMS')],
            ['BASE DE CÁLC. ICMS ST', $this->textoNumero($this->ICMSTot, 'vBCST')],
            ['VALOR DO ICMS ST', $this->textoNumero($this->ICMSTot, 'vST')],
            ['VALOR TOTAL IPI', $this->textoNumero($this->ICMSTot, 'vIPI')],
        ]);
        $opcionais = $this->camposIcmsOpcionais();
        if (!empty($opcionais)) {
            $y = $this->linhaTotais($x, $y, $w, $opcionais);
        }

        $ibs = $this->dom->getElementsByTagName('IBSCBSTot')->item(0);
        $isTot = $this->dom->getElementsByTagName('ISTot')->item(0);
        $y = $this->tituloBloco($x, $y, $w, 'TOTAL DO IBS / CBS / IS');
        $y = $this->linhaTotais($x, $y, $w, [
            ['IBS UF', $this->textoNumero($ibs, 'vIBSUF')],
            ['IBS MUNICIPAL', $this->textoNumero($ibs, 'vIBSMun')],
            ['CBS', $this->textoNumero($ibs, 'vCBS')],
            ['IS', $this->textoNumero($isTot, 'vIS')],
        ]);
        $mono = $this->camposIbsCbsOpcionais();
        if (!empty($mono)) {
            $y = $this->linhaTotais($x, $y, $w, $mono);
        }

        return $y;
    }

    /**
     * Grade em faixa: identificação do item e, abaixo, base, alíquota e valor.
     *
     * @param float $x
     * @param float $y
     * @param int $nInicio
     * @param float $hmax
     * @param int $pag
     * @param int $totpag
     * @param float $hCabecItens
     * @return float
     */
    protected function itens($x, $y, &$nInicio, $hmax, $pag = 0, $totpag = 0, $hCabecItens = 7)
    {
        if (isset($this->medidaRtc) && $pag == 1) {
            $this->medidaRtc['yItens'] = $y;
            $this->medidaRtc['hDispo1'] = $hmax;
        }
        $oldX = $x;
        $oldY = $y;
        $w = $this->larguraUtil($pag);
        $aFont = ['font' => $this->fontePadrao, 'size' => 7, 'style' => 'B'];
        $this->pdf->textBox($x, $y, $w, 4, 'DADOS DOS PRODUTOS / SERVIÇOS', $aFont, 'T', 'L', 0, '');
        $y += 3;
        $hmax += 1;
        $this->pdf->textBox($x, $y, $w, $hmax);
        $this->cabecalhoItens($x, $y, $w);
        $y += 8;

        $totItens = $this->det->length;
        $i = 0;
        $hUsado = $hCabecItens;
        foreach ($this->det as $det) {
            if ($i >= $nInicio) {
                $h = $this->calculeHeight($det, false);
                $hUsado += $h;
                if (($hmax - $hUsado) < 1 && $i < $totItens) {
                    if ($pag == $totpag) {
                        $totpag++;
                    }
                    $nInicio = $i;
                    break;
                }
                $this->faixaItem($oldX, $y, $w, $h, $det);
                $y += $h;
                $this->qtdeItensProc++;
            }
            $i++;
        }

        return $oldY + $hmax;
    }

    /**
     * @param \DOMElement $item
     * @param bool $mostrarUnidadeTributavel
     * @return float
     */
    protected function calculeHeight($item, $mostrarUnidadeTributavel = false)
    {
        return $this->medidaFaixa($item)['h'];
    }

    /**
     * @return int
     */
    protected function alturaImpostoRtc()
    {
        $linhasOpcionais = (int) ceil(count($this->camposIcmsOpcionais()) / 6);
        $linhasOpcionais += (int) ceil(count($this->camposIbsCbsOpcionais()) / 6);
        $h = (3 + 7) * 3;
        $h += $linhasOpcionais * 7;

        return $h + 1;
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    protected function camposIcmsOpcionais()
    {
        $campos = [];
        $mapa = [
            'vFCP' => 'FCP',
            'vFCPST' => 'FCP RETIDO POR ST',
            'vICMSUFDest' => 'ICMS UF DEST.',
            'vICMSUFRemet' => 'ICMS UF REMET.',
            'vFCPUFDest' => 'FCP UF DEST.',
            'vICMSMono' => 'ICMS MONO',
            'vICMSMonoReten' => 'ICMS MONO RETENÇÃO',
        ];
        foreach ($mapa as $tag => $titulo) {
            $valor = $this->textoNumeroOpcional($this->ICMSTot, $tag);
            if ($valor !== '') {
                $campos[] = [$titulo, $valor];
            }
        }

        return $campos;
    }

    /**
     * IBS/CBS monofásicos (item 4.1 da NT), no quadro do IBS/CBS/IS e só com valor.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function camposIbsCbsOpcionais()
    {
        $campos = [];
        if (empty($this->dom)) {
            return $campos;
        }
        $tot = $this->dom->getElementsByTagName('IBSCBSTot')->item(0);
        $gMono = $tot ? $tot->getElementsByTagName('gMono')->item(0) : null;
        if ($gMono) {
            $mapaMono = [
                'vIBSMono' => 'IBS MONO',
                'vCBSMono' => 'CBS MONO',
                'vIBSMonoReten' => 'IBS MONO RETENÇÃO',
                'vCBSMonoReten' => 'CBS MONO RETENÇÃO',
            ];
            foreach ($mapaMono as $tag => $titulo) {
                $valor = $this->textoNumeroOpcional($gMono, $tag);
                if ($valor !== '') {
                    $campos[] = [$titulo, $valor];
                }
            }
        }

        return $campos;
    }

    /**
     * @param float $x
     * @param float $y
     * @param float $w
     * @param string $texto
     * @return float
     */
    protected function tituloBloco($x, $y, $w, $texto)
    {
        $aFont = ['font' => $this->fontePadrao, 'size' => 7, 'style' => 'B'];
        $this->pdf->textBox($x, $y, $w, 4, $texto, $aFont, 'T', 'L', 0, '');

        return $y + 3;
    }

    /**
     * @param float $x
     * @param float $y
     * @param float $w
     * @param array<int, array{0: string, 1: string}> $campos
     * @return float
     */
    protected function linhaTotais($x, $y, $w, array $campos)
    {
        $n = max(1, count($campos));
        $largura = $w / $n;
        $h = 7;
        $cursor = $x;
        foreach ($campos as $campo) {
            $this->caixa($cursor, $y, $largura, $h, $campo[0], $campo[1], 9);
            $cursor += $largura;
        }

        return $y + $h;
    }

    /**
     * @param float $x
     * @param float $y
     * @param float $w
     * @param float $h
     * @param string $titulo
     * @param string $valor
     * @param int $tamanhoValor
     * @return void
     */
    protected function caixa($x, $y, $w, $h, $titulo, $valor, $tamanhoValor)
    {
        $fontTitulo = ['font' => $this->fontePadrao, 'size' => 5, 'style' => ''];
        $fontValor = ['font' => $this->fontePadrao, 'size' => $tamanhoValor, 'style' => 'B'];
        $this->pdf->textBox($x, $y, $w, $h, $titulo, $fontTitulo, 'T', 'L', 1, '');
        $this->pdf->textBox($x, $y, $w, $h, $valor, $fontValor, 'B', 'R', 0, '');
    }

    /**
     * @param float $x
     * @param float $y
     * @param float $w
     * @return void
     */
    protected function cabecalhoItens($x, $y, $w)
    {
        $cols = $this->colunasItem($w);
        $aFont = ['font' => $this->fontePadrao, 'size' => 5, 'style' => 'B'];
        $h = 8;
        $labels = [
            "DESCRIÇÃO DO PRODUTO / SERVIÇO",
            "CST / CFOP",
            "QTD / UN",
            "VLR UNIT",
            "VLR TOTAL",
            "BASES DE CÁLCULO",
            "ALÍQUOTAS",
            "VALOR DOS TRIBUTOS",
        ];
        foreach ($cols as $i => $largura) {
            $this->pdf->textBox($x, $y, $largura, $h, $labels[$i], $aFont, 'C', 'C', 1, '', false);
            $x += $largura;
        }
    }

    /**
     * Uma faixa por item, como no modelo retrato da NT 2026.010 (página 8): descrição com
     * [NCM] [cClassTrib] no rodapé e os tributos rotulados, dois por linha nas alíquotas e valores.
     *
     * @param float $x
     * @param float $y
     * @param float $w
     * @param float $h
     * @param \DOMElement $det
     * @return void
     */
    protected function faixaItem($x, $y, $w, $h, $det)
    {
        $medida = $this->medidaFaixa($det);
        $cols = $this->colunasItem($w);
        $lh = $medida['lh'];
        $hMiolo = $medida['hMiolo'];
        $prod = $det->getElementsByTagName('prod')->item(0);
        $aFont = ['font' => $this->fontePadrao, 'size' => 6, 'style' => ''];
        $aFontMiudo = ['font' => $this->fontePadrao, 'size' => 5, 'style' => ''];

        $cursor = $x;
        foreach ($cols as $i => $largura) {
            if ($i > 0) {
                $this->pdf->line($cursor, $y, $cursor, $y + $hMiolo);
            }
            $cursor += $largura;
        }

        $this->pdf->textBox($x, $y, $cols[0] - 1, $lh * $medida['nNome'], $medida['nome'], $aFont, 'T', 'L', 0, '', false);
        if ($medida['info'] !== '') {
            $this->pdf->textBox(
                $x,
                $y + ($lh * $medida['nNome']),
                $cols[0] - 1,
                $lh * $medida['nInfo'],
                $medida['info'],
                $aFontMiudo,
                'T',
                'L',
                0,
                '',
                false
            );
        }
        $this->pdf->textBox($x, $y + $hMiolo - $lh, $cols[0], $lh, $medida['rodape'], $aFontMiudo, 'T', 'L', 0, '');

        $xCst = $x + $cols[0];
        $this->rotuloValor($xCst, $y, $cols[1], $lh, 'CST', $this->cstItem($det));
        $this->rotuloValor($xCst, $y + $lh, $cols[1], $lh, 'CFOP', $this->getTagValue($prod, 'CFOP'));

        $xQtd = $xCst + $cols[1];
        $un = $this->getTagValue($prod, 'uCom');
        $this->pdf->textBox($xQtd, $y, $cols[2], $lh, $this->textoQuantidade($prod, 'qCom'), $aFont, 'T', 'R', 0, '');
        $this->pdf->textBox($xQtd, $y + $lh, $cols[2], $lh, $un === '' ? '' : '[' . $un . ']', $aFontMiudo, 'T', 'C', 0, '');

        $xUnit = $xQtd + $cols[2];
        $this->pdf->textBox($xUnit, $y, $cols[3], $lh, $this->textoUnitario($prod), $aFont, 'T', 'R', 0, '');
        $xTotal = $xUnit + $cols[3];
        $this->pdf->textBox($xTotal, $y, $cols[4], $lh, $this->textoNumero($prod, 'vProd'), $aFont, 'T', 'R', 0, '');

        $trib = $this->tributosItem($det);
        $xBase = $xTotal + $cols[4];
        foreach ($trib['bases'] as $i => $par) {
            $this->rotuloValor($xBase, $y + ($i * $lh), $cols[5], $lh, $par[0], $par[1]);
        }
        $xAliq = $xBase + $cols[5];
        $xValor = $xAliq + $cols[6];
        foreach ($trib['linhas'] as $i => $linha) {
            $yLinha = $y + ($i * $lh);
            $this->parRotulado($xAliq, $yLinha, $cols[6], $lh, $linha[0], $linha[1]);
            $this->parRotulado($xValor, $yLinha, $cols[7], $lh, $linha[2], $linha[3]);
        }

        $this->pdf->dashedHLine($x, $y + $hMiolo + 0.3, $w, 0.1, 120);
    }

    /**
     * Rótulo à esquerda e valor à direita na mesma célula ("ICMS ...... 0,00").
     *
     * @param float $x
     * @param float $y
     * @param float $w
     * @param float $h
     * @param string $rotulo
     * @param string $valor
     * @return void
     */
    protected function rotuloValor($x, $y, $w, $h, $rotulo, $valor, $tamanho = 5)
    {
        $aFont = ['font' => $this->fontePadrao, 'size' => $tamanho, 'style' => ''];
        $this->pdf->textBox($x, $y, $w, $h, $rotulo, $aFont, 'T', 'L', 0, '');
        $this->pdf->textBox($x, $y, $w, $h, $valor, $aFont, 'T', 'R', 0, '');
    }

    /**
     * Dois tributos na mesma linha da célula, separados por "/" ("ICMS 0,00% / CBS 0,00%").
     *
     * @param float $x
     * @param float $y
     * @param float $w
     * @param float $h
     * @param array{0: string, 1: string} $esquerda
     * @param array{0: string, 1: string} $direita
     * @return void
     */
    protected function parRotulado($x, $y, $w, $h, array $esquerda, array $direita)
    {
        $aFont = ['font' => $this->fontePadrao, 'size' => 4.5, 'style' => ''];
        $meio = $w / 2;
        $this->rotuloValor($x, $y, $meio - 1, $h, $esquerda[0], $esquerda[1], 4.5);
        $this->pdf->textBox($x + $meio - 1, $y, 2, $h, '/', $aFont, 'T', 'C', 0, '');
        $this->rotuloValor($x + $meio + 1, $y, $meio - 1, $h, $direita[0], $direita[1], 4.5);
    }

    /**
     * Altura da faixa desenhada por faixaItem(): a maior entre a descrição (nome,
     * informação adicional e a linha do NCM) e as quatro linhas das bases de cálculo.
     *
     * @param \DOMElement $det
     * @return array{h: float, hMiolo: float, lh: float, nNome: int, nInfo: int, nome: string, info: string, rodape: string}
     */
    protected function medidaFaixa($det)
    {
        $cols = $this->colunasItem($this->larguraUtil(1));
        $wDesc = max(10, $cols[0] - 1);
        $aFont = ['font' => $this->fontePadrao, 'size' => 6, 'style' => ''];
        $aFontMiudo = ['font' => $this->fontePadrao, 'size' => 5, 'style' => ''];
        $lh = 2.3;
        $prod = $det->getElementsByTagName('prod')->item(0);
        $nome = $prod ? $this->getTagValue($prod, 'xProd') : '';
        $info = $this->getTagValue($det, 'infAdProd');
        $nNome = max(1, (int) $this->pdf->getNumLines($nome, $wDesc, $aFont));
        $nInfo = $info === '' ? 0 : max(1, (int) $this->pdf->getNumLines($info, $wDesc, $aFontMiudo));
        $hMiolo = max(($nNome + $nInfo + 1) * $lh, 4 * $lh);

        return [
            'h' => round($hMiolo + 0.8, 2),
            'hMiolo' => $hMiolo,
            'lh' => $lh,
            'nNome' => $nNome,
            'nInfo' => $nInfo,
            'nome' => $nome,
            'info' => $info,
            'rodape' => $this->rodapeItem($det),
        ];
    }

    /**
     * Proporções das colunas do modelo retrato da NT 2026.010.
     *
     * @param float $w
     * @return float[]
     */
    protected function colunasItem($w)
    {
        $partes = [0.21, 0.06, 0.07, 0.08, 0.08, 0.125, 0.165];
        $cols = [];
        $usado = 0;
        foreach ($partes as $parte) {
            $largura = round($w * $parte, 0);
            $cols[] = $largura;
            $usado += $largura;
        }
        $cols[] = $w - $usado;

        return $cols;
    }

    /**
     * Bases (uma por linha) e pares de alíquota/valor na ordem do modelo:
     * ICMS / CBS, IBS UF / IPI, IBS MUN / IS. Tag ausente permanece em branco.
     *
     * @param \DOMElement $det
     * @return array{bases: array<int, array{0: string, 1: string}>, linhas: array<int, array>}
     */
    protected function tributosItem($det)
    {
        $imposto = $det->getElementsByTagName('imposto')->item(0);
        $icms = $imposto ? $imposto->getElementsByTagName('ICMS')->item(0) : null;
        $ipi = $imposto ? $imposto->getElementsByTagName('IPI')->item(0) : null;
        $is = $this->primeiroFilho($imposto, 'IS');
        $gIbsCbs = $this->primeiroFilho($this->primeiroFilho($imposto, 'IBSCBS'), 'gIBSCBS');
        $gIbsUf = $this->primeiroFilho($gIbsCbs, 'gIBSUF');
        $gIbsMun = $this->primeiroFilho($gIbsCbs, 'gIBSMun');
        $gCbs = $this->primeiroFilho($gIbsCbs, 'gCBS');

        return [
            'bases' => [
                ['ICMS', $this->textoNumero($icms, 'vBC')],
                ['IBS / CBS', $this->textoNumero($gIbsCbs, 'vBC')],
                ['IS', $this->textoNumero($is, 'vBCIS')],
                ['IPI', $this->textoNumero($ipi, 'vBC')],
            ],
            'linhas' => [
                [
                    ['ICMS', $this->textoPercentual($icms, 'pICMS')],
                    ['CBS', $this->textoPercentual($gCbs, 'pCBS')],
                    ['ICMS', $this->textoNumero($icms, 'vICMS')],
                    ['CBS', $this->textoNumero($gCbs, 'vCBS')],
                ],
                [
                    ['IBS UF', $this->textoPercentual($gIbsUf, 'pIBSUF')],
                    ['IPI', $this->textoPercentual($ipi, 'pIPI')],
                    ['IBS UF', $this->textoNumero($gIbsUf, 'vIBSUF')],
                    ['IPI', $this->textoNumero($ipi, 'vIPI')],
                ],
                [
                    ['IBS MUN', $this->textoPercentual($gIbsMun, 'pIBSMun')],
                    ['IS', $this->textoPercentual($is, 'pIS')],
                    ['IBS MUN', $this->textoNumero($gIbsMun, 'vIBSMun')],
                    ['IS', $this->textoNumero($is, 'vIS')],
                ],
            ],
        ];
    }

    /**
     * @param \DOMElement $det
     * @return string
     */
    protected function rodapeItem($det)
    {
        $prod = $det->getElementsByTagName('prod')->item(0);
        $ncm = $prod ? $this->getTagValue($prod, 'NCM') : '';
        $classe = $this->cClassTribItem($det);
        $partes = [];
        if ($ncm !== '') {
            $partes[] = '[NCM ' . $ncm . ']';
        }
        if ($classe !== '') {
            $partes[] = '[cClassTrib ' . $classe . ']';
        }

        return implode(' ', $partes);
    }

    /**
     * @param \DOMElement|null $grupo
     * @param string $tagCheia
     * @return string
     */
    protected function textoPercentual($grupo, $tagCheia)
    {
        $numero = $this->textoAliquota($grupo, $tagCheia);

        return $numero === '' ? '' : $numero . '%';
    }

    /**
     * @param \DOMElement $det
     * @return string
     */
    protected function cstItem($det)
    {
        $icms = $det->getElementsByTagName('ICMS')->item(0);
        if (empty($icms)) {
            return '';
        }
        $cst = $this->getTagValue($icms, 'CST') . $this->getTagValue($icms, 'CSOSN');
        if ($cst === '') {
            return '';
        }

        return $this->getTagValue($icms, 'orig') . '/' . $cst;
    }

    /**
     * @param \DOMElement $det
     * @return string
     */
    protected function cClassTribItem($det)
    {
        $imposto = $det->getElementsByTagName('imposto')->item(0);
        $ibscbs = $this->primeiroFilho($imposto, 'IBSCBS');

        return $this->getTagValue($ibscbs, 'cClassTrib');
    }

    /**
     * Com gRed, a alíquota impressa é a efetiva. Sem a tag, usa a alíquota cheia.
     *
     * @param \DOMElement|null $grupo
     * @param string $tagCheia
     * @return string
     */
    protected function textoAliquota($grupo, $tagCheia)
    {
        if (empty($grupo)) {
            return '';
        }
        $gRed = $this->primeiroFilho($grupo, 'gRed');
        if (!empty($gRed)) {
            return $this->textoNumero($gRed, 'pAliqEfet');
        }

        return $this->textoNumero($grupo, $tagCheia);
    }

    /**
     * @param \DOMNode|null $node
     * @param string $tag
     * @return string
     */
    protected function textoNumero($node, $tag)
    {
        if (empty($node)) {
            return '';
        }
        $direto = $this->primeiroFilho($node, $tag);
        $campo = $direto ?: $node->getElementsByTagName($tag)->item(0);
        if (empty($campo) || $campo->nodeValue === '') {
            return '';
        }

        return number_format((float) $campo->nodeValue, 2, ',', '.');
    }

    /**
     * Campo facultativo (item 4.4 da NT): o grupo pode vir no XML zerado
     * quando não se aplica à operação, e aí o quadro não é impresso.
     *
     * @param \DOMNode|null $node
     * @param string $tag
     * @return string
     */
    protected function textoNumeroOpcional($node, $tag)
    {
        $texto = $this->textoNumero($node, $tag);
        if ($texto === '' || (float) str_replace(['.', ','], ['', '.'], $texto) == 0) {
            return '';
        }

        return $texto;
    }

    /**
     * Valor unitário com as casas de vUnComCasasDec, como na Danfe.
     *
     * @param \DOMElement|null $prod
     * @return string
     */
    protected function textoUnitario($prod)
    {
        $campo = $this->primeiroFilho($prod, 'vUnCom');
        if (empty($campo) || $campo->nodeValue === '') {
            return '';
        }

        return number_format((float) $campo->nodeValue, $this->vUnComCasasDec, ',', '.');
    }

    /**
     * @param \DOMNode|null $node
     * @param string $tag
     * @return string
     */
    protected function textoQuantidade($node, $tag)
    {
        if (empty($node)) {
            return '';
        }
        $campo = $this->primeiroFilho($node, $tag);
        if (empty($campo)) {
            $campo = $node->getElementsByTagName($tag)->item(0);
        }
        if (empty($campo) || $campo->nodeValue === '') {
            return '';
        }
        $texto = number_format((float) $campo->nodeValue, 4, ',', '.');

        return rtrim(rtrim($texto, '0'), ',');
    }

    /**
     * @param \DOMNode|null $node
     * @param string $nome
     * @return \DOMElement|null
     */
    protected function primeiroFilho($node, $nome)
    {
        if (empty($node) || empty($node->childNodes)) {
            return null;
        }
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && $child->localName === $nome) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @param string $crt
     * @return string
     */
    protected function rotuloCrt($crt)
    {
        $mapa = [
            '1' => '1 - Simples Nacional',
            '2' => '2 - Simples Nacional, excesso',
            '3' => '3 - Regime Normal',
            '4' => '4 - MEI',
        ];

        return isset($mapa[$crt]) ? $mapa[$crt] : (string) $crt;
    }

    /**
     * @param int|string $pag
     * @return float
     */
    protected function larguraUtil($pag)
    {
        if ($this->orientacao === 'P' || (int) $pag !== 1) {
            return $this->wPrint;
        }

        return $this->wPrint - $this->wCanhoto;
    }
}
