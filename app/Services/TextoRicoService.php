<?php

namespace App\Services;

/**
 * Texto com formatação vindo do editor rico (descrição da tarefa de
 * Projetos): negrito, itálico, sublinhado, riscado, cor do texto,
 * marca-texto, listas e links. Allowlist FECHADA via DOMDocument -- tag
 * fora da lista some (o texto de dentro fica), script/style somem com o
 * conteúdo, e só sobrevivem os atributos validados aqui. Separado do
 * sanitizador da Base de Conhecimento (KbService), que tem regras próprias.
 */
class TextoRicoService
{
    private const TAGS = ['b', 'strong', 'i', 'em', 'u', 's', 'strike', 'span', 'font', 'br', 'div', 'p', 'ul', 'ol', 'li', 'a', 'hr', 'h5', 'blockquote'];
    private const TAGS_REMOVER_COM_CONTEUDO = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'noscript'];

    /** Limpa o HTML do editor antes de gravar. Endereços soltos no texto viram link. */
    public function sanitizar(?string $html): ?string
    {
        $html = trim((string)$html);
        if (!$this->pareceHtml($html)) {
            $html = nl2br(htmlspecialchars($html), false); // texto puro (sem editor): mantém as quebras de linha
        } elseif (str_contains($html, "
") && !preg_match('~<(?:div|p|br|li)[\s>/]~i', $html)) {
            $html = nl2br($html, false);
        }
        if ($html === '' || trim(strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], ' ', $html))) === '' && !preg_match('/<(?:li|hr)/i', $html)) {
            return null;
        }

        // Sem a extensão DOM (php-xml, em Infraestrutura > Dependências): guarda como texto puro, nunca HTML cru.
        if (!class_exists(\DOMDocument::class)) {
            $texto = trim(html_entity_decode(strip_tags(preg_replace('~<(?:br|/div|/p|/li|hr)[^>]*>~i', "
", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            return $texto !== '' ? nl2br(htmlspecialchars($texto), false) : null;
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div id="rd-texto-rico">' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
        libxml_clear_errors();

        $raiz = $dom->getElementById('rd-texto-rico');
        if ($raiz === null) {
            return null;
        }

        $this->limpar($raiz, $dom);
        $this->linkificarTexto($raiz, $dom);

        $saida = '';
        foreach (iterator_to_array($raiz->childNodes) as $filho) {
            $saida .= $dom->saveHTML($filho);
        }

        return trim($saida) !== '' ? trim($saida) : null;
    }

    /**
     * Pra exibir/carregar no editor: HTML do editor passa de novo pelo
     * sanitizador (defesa extra); texto antigo (anterior ao editor) é escapado,
     * com quebras de linha e links.
     */
    public function paraHtml(?string $valor): string
    {
        $valor = (string)$valor;
        if ($valor === '') {
            return '';
        }
        if ($this->pareceHtml($valor)) {
            return (string)$this->sanitizar($valor);
        }

        return nl2br(linkificar(htmlspecialchars($valor)), false);
    }

    private function pareceHtml(string $valor): bool
    {
        return (bool)preg_match('~<(?:b|strong|i|em|u|s|strike|span|font|br|div|p|ul|ol|li|a|hr|h5|blockquote)(?:\s[^>]*)?/?>~i', $valor);
    }

    private function limpar(\DOMNode $no, \DOMDocument $dom): void
    {
        foreach (iterator_to_array($no->childNodes) as $filho) {
            if ($filho instanceof \DOMText) {
                continue;
            }
            if (!($filho instanceof \DOMElement)) {
                $no->removeChild($filho); // comentários, etc.
                continue;
            }

            $tag = strtolower($filho->tagName);
            if (in_array($tag, self::TAGS_REMOVER_COM_CONTEUDO, true)) {
                $no->removeChild($filho);
                continue;
            }
            if (!in_array($tag, self::TAGS, true)) {
                while ($filho->firstChild) {
                    $no->insertBefore($filho->firstChild, $filho);
                }
                $no->removeChild($filho);
                continue;
            }

            // Lê só o que é permitido ANTES de zerar os atributos crus.
            $estilos = [];
            $marcas = [];
            $href = null;
            if (in_array($tag, ['span', 'font', 'div', 'p'], true)) {
                $estilos = $this->estilosPermitidos($filho->getAttribute('style'));
                $marcas = $this->marcasDoEstilo($filho->getAttribute('style'));
                if ($tag === 'font' && preg_match('/^#[0-9a-f]{6}$/i', trim($filho->getAttribute('color')))) {
                    $estilos['color'] = strtolower(trim($filho->getAttribute('color')));
                }
                if ($tag === 'font' && isset(self::TAMANHO_FONT[(int)$filho->getAttribute('size')])) {
                    $estilos['font-size'] = self::TAMANHO_FONT[(int)$filho->getAttribute('size')] . 'px';
                }
            }
            if ($tag === 'a') {
                $alvo = trim($filho->getAttribute('href'));
                $href = preg_match('~^https?://~i', $alvo) ? $alvo : null;
            }

            foreach (iterator_to_array($filho->attributes ?? []) as $attr) {
                $filho->removeAttribute($attr->name);
            }

            // <font color> (execCommand antigo) vira <span style="color:..">.
            if ($tag === 'font') {
                $span = $dom->createElement('span');
                while ($filho->firstChild) {
                    $span->appendChild($filho->firstChild);
                }
                $no->replaceChild($span, $filho);
                $filho = $span;
            }

            if ($estilos) {
                $filho->setAttribute('style', implode(';', array_map(fn ($k, $v) => "{$k}:{$v}", array_keys($estilos), $estilos)));
            }
            // Negrito/itálico/sublinhado/riscado em style (Chrome, Word, Google Docs) viram <b>/<i>/<u>/<s>.
            foreach ($marcas as $marca) {
                $embrulho = $dom->createElement($marca);
                while ($filho->firstChild) {
                    $embrulho->appendChild($filho->firstChild);
                }
                $filho->appendChild($embrulho);
            }
            if ($tag === 'a') {
                if ($href === null) { // link sem http(s): fica só o texto
                    while ($filho->firstChild) {
                        $no->insertBefore($filho->firstChild, $filho);
                    }
                    $no->removeChild($filho);
                    continue;
                }
                $filho->setAttribute('href', $href); // mesma ordem do linkificar(): salvar de novo não muda nada
                $filho->setAttribute('target', '_blank');
                $filho->setAttribute('rel', 'noopener noreferrer');
                $filho->setAttribute('title', $href);
            }

            $this->limpar($filho, $dom);
        }
    }

    /** @return string[] tags equivalentes ao que o style pede (font-weight bold, italic, underline, line-through) */
    private function marcasDoEstilo(string $estilo): array
    {
        $estilo = strtolower($estilo);
        $marcas = [];
        if (preg_match('/font-weight\s*:\s*(bold|bolder|[6-9]00)/', $estilo)) {
            $marcas[] = 'b';
        }
        if (preg_match('/font-style\s*:\s*italic/', $estilo)) {
            $marcas[] = 'i';
        }
        if (preg_match('/text-decoration(?:-line)?\s*:[^;]*underline/', $estilo)) {
            $marcas[] = 'u';
        }
        if (preg_match('/text-decoration(?:-line)?\s*:[^;]*line-through/', $estilo)) {
            $marcas[] = 's';
        }

        return $marcas;
    }

    /** <font size="1..7"> (comando nativo/colado de fora) -> px. */
    private const TAMANHO_FONT = [1 => 10, 2 => 12, 3 => 14, 4 => 16, 5 => 20, 6 => 24, 7 => 32];

    /**
     * Só cor do texto e marca-texto (#rrggbb ou rgb(), o navegador costuma
     * mandar rgb) e tamanho da fonte (px de 10 a 36; pt/em/palavras viram px).
     */
    private function estilosPermitidos(string $estilo): array
    {
        $saida = [];
        foreach (explode(';', $estilo) as $declaracao) {
            [$prop, $valor] = array_map('trim', array_pad(explode(':', $declaracao, 2), 2, ''));
            $prop = strtolower($prop);
            if ($prop === 'font-size') {
                $px = $this->tamanhoPx($valor);
                if ($px !== null) {
                    $saida['font-size'] = $px . 'px';
                }
                continue;
            }
            if (!in_array($prop, ['color', 'background-color'], true)) {
                continue;
            }
            $hex = $this->corHex($valor);
            if ($hex !== null && !($prop === 'background-color' && $hex === '#ffffff')) {
                $saida[$prop] = $hex;
            }
        }

        return $saida;
    }

    private function tamanhoPx(string $valor): ?int
    {
        $valor = strtolower(trim($valor));
        $palavras = ['x-small' => 10, 'small' => 12, 'medium' => 14, 'large' => 18, 'x-large' => 24, 'xx-large' => 32, 'xxx-large' => 36];
        if (isset($palavras[$valor])) {
            $px = $palavras[$valor];
        } elseif (preg_match('/^(\d+(?:\.\d+)?)(px|pt|em|rem)$/', $valor, $m)) {
            $px = match ($m[2]) {
                'pt' => (float)$m[1] * 4 / 3,
                'em', 'rem' => (float)$m[1] * 14,
                default => (float)$m[1],
            };
        } else {
            return null;
        }

        return (int)round(max(10, min(36, $px)));
    }

    private function corHex(string $valor): ?string
    {
        $valor = strtolower(trim($valor));
        if (preg_match('/^#[0-9a-f]{6}$/', $valor)) {
            return $valor;
        }
        if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*([\d.]+))?\s*\)$/', $valor, $m)) {
            if (isset($m[4]) && (float)$m[4] === 0.0) {
                return null; // transparente
            }
            return sprintf('#%02x%02x%02x', min(255, (int)$m[1]), min(255, (int)$m[2]), min(255, (int)$m[3]));
        }

        return null;
    }

    /** Endereço digitado/colado no texto (fora de um <a>) vira link. */
    private function linkificarTexto(\DOMNode $no, \DOMDocument $dom): void
    {
        foreach (iterator_to_array($no->childNodes) as $filho) {
            if ($filho instanceof \DOMElement) {
                if (strtolower($filho->tagName) !== 'a') {
                    $this->linkificarTexto($filho, $dom);
                }
                continue;
            }
            if (!($filho instanceof \DOMText) || !preg_match('~(?:https?://|www\.)~i', $filho->nodeValue)) {
                continue;
            }

            $fragmento = $dom->createDocumentFragment();
            $html = linkificar(htmlspecialchars($filho->nodeValue), PHP_INT_MAX); // no texto salvo o endereço fica inteiro
            if (@$fragmento->appendXML(str_replace('&nbsp;', '&#160;', $html))) {
                $no->replaceChild($fragmento, $filho);
            }
        }
    }
}
