# Banco de questões do cartão "Questões de concurso"

`questoes.jsonl.gz` tem **2.604 questões** de múltipla escolha com gabarito, uma por linha (JSON):
`banca, prova, ano, materia, assunto, dificuldade, enunciado, alts[5], certa (0 = A), fonte, ref`.

A tabela `questoes_banco` é preenchida a partir deste arquivo pelo `QuestoesBancoSeeder`
(`php artisan db:seed --class=QuestoesBancoSeeder`) ou, se ninguém rodou o seeder, na primeira
busca do cartão (`App\Support\QuestoesBanco::garantir()`), porque a hospedagem não tem terminal.

## Fontes e licenças

| Conjunto | Banca | Provas | Questões | Licença |
|---|---|---|---|---|
| [legal-nlp/oab-exams](https://github.com/legal-nlp/oab-exams) (`official/raw`) — Rademaker, Cuconato, Passos e Delfino, 2018 | FGV (e Cespe/Cebraspe no exame 2010.1) | Exame de Ordem Unificado da OAB, 2010.1 a XXV (2018) | 2.209 | MIT (`LICENCA-OAB-MIT.txt`) |
| [maritaca-ai/enem](https://huggingface.co/datasets/maritaca-ai/enem) — Pires, Almeida, Abonizio e Nogueira, 2023 | INEP | ENEM 2022, 2023 e 2024 | 395 | Apache-2.0 |

As provas em si são documentos públicos do INEP (ENEM) e da OAB. Nada foi copiado de sites de questões
(QConcursos, TEC Concursos e semelhantes proíbem a cópia).

## O que entrou e o que foi calculado

- Ficaram de fora as questões anuladas, as sem gabarito e as do ENEM que dependem de imagem (gráfico,
  figura, tirinha), porque o cartão mostra só texto.
- **Matéria**: no ENEM, pela posição da questão (1–45 Linguagens, 46–90 Humanas, 91–135 Natureza,
  136–180 Matemática). Na OAB, a área marcada no conjunto; nas provas sem marcação (exames XV a XXV),
  um classificador de palavras treinado nas provas marcadas, respeitando a ordem fixa das matérias na
  prova da FGV. Pode haver erro nas fronteiras entre duas matérias.
- **Assunto**: palavras-chave por matéria (por exemplo "Contratos", "Probabilidade"); sem palavra-chave,
  fica "Geral".
- **Dificuldade**: *estimada* pelo tamanho do enunciado e das alternativas (terços dentro de cada matéria).
  Não é a dificuldade oficial; o cartão mostra "estimada".

## Refazer o arquivo

1. Baixe numa pasta vazia:
   - `https://raw.githubusercontent.com/legal-nlp/oab-exams/master/official/raw/<exame>.txt`
     (2010-01, 2010-02, 2011-03 … 2018-25, inclusive 2012-06a e 2016-20a);
   - `https://huggingface.co/datasets/maritaca-ai/enem/resolve/main/<ano>.jsonl` salvos como
     `maritaca2022.jsonl`, `maritaca2023.jsonl` e `maritaca2024.jsonl`.
2. `php database/data/questoes/construir.php <pasta>`
3. Para recarregar a tabela: `php artisan db:seed --class=QuestoesBancoSeeder` (apaga e importa de novo).
