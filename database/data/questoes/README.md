# Banco de questões do cartão "Questões de concurso"

São **4.053 questões** de múltipla escolha (ou certo/errado) com gabarito, em três tipos de arquivo:

| Arquivo | O que tem | Questões |
|---|---|---|
| `questoes.jsonl.gz` | OAB (FGV e Cespe) 2010–2018 e ENEM 2022–2024 | 2.604 |
| `enem-2009-2017.jsonl.gz` | ENEM 2009–2017 (inclui a 2ª aplicação de 2016) | 1.059 |
| `originais/*.json` | questões **inéditas** do Work Flower, com comentário, uma área por arquivo | 390 |

Linha dos `.jsonl.gz` (uma questão JSON por linha):
`banca, prova, ano, materia, assunto, dificuldade, enunciado, alts[5], certa (0 = A), fonte, ref`
(opcionais: `area`, `comentario`). O formato das inéditas está em `originais/FORMATO.md`.

A tabela `questoes_banco` é preenchida a partir de **todos** esses arquivos pelo `QuestoesBancoSeeder`
(`php artisan db:seed --class=QuestoesBancoSeeder`) ou, se ninguém rodou o seeder, na primeira busca do cartão
(`App\Support\QuestoesBanco::garantir()`), porque a hospedagem não tem terminal. Quando entra um arquivo novo
ou um arquivo muda de tamanho, a assinatura (`QuestoesBanco::versao()`) muda e a próxima busca importa tudo de novo.
Para acrescentar questões, basta pôr outro `.jsonl.gz` nesta pasta ou outro `.json` em `originais/` (com `ref` únicas).

## Áreas

| Área | De onde vêm | Questões |
|---|---|---|
| ENEM | INEP 2009–2017 e 2022–2024 (Linguagens, Ciências Humanas, Ciências da Natureza, Matemática) | 1.454 |
| Direito | OAB 2010–2018 (2.209) + inéditas de Constitucional, Administrativo, Penal e Lei 8.112 (45) | 2.254 |
| Tecnologia | inéditas: Programação, Estruturas de dados, Engenharia de software, Web, Redes, Banco de dados, Segurança, SO e hardware | 155 |
| Administração | inéditas: Administração geral e pública, Gestão de pessoas, Arquivologia | 40 |
| Português | inéditas: Língua Portuguesa | 45 |
| Raciocínio lógico e matemática | inéditas: Raciocínio lógico, Matemática, Matemática financeira | 45 |
| Saúde | inéditas: SUS e saúde pública, Epidemiologia, Enfermagem, Ciências da saúde | 60 |

## Fontes e licenças

| Conjunto | Banca | Provas | Licença |
|---|---|---|---|
| [legal-nlp/oab-exams](https://github.com/legal-nlp/oab-exams) (`official/raw`) — Rademaker, Cuconato, Passos e Delfino, 2018 | FGV (e Cespe/Cebraspe no exame 2010.1) | Exame de Ordem Unificado da OAB, 2010.1 a XXV (2018) | MIT (`LICENCA-OAB-MIT.txt`) |
| [maritaca-ai/enem](https://huggingface.co/datasets/maritaca-ai/enem) — Pires, Almeida, Abonizio e Nogueira, 2023 | INEP | ENEM 2022, 2023 e 2024 | Apache-2.0 |
| [eduagarcia/enem_challenge](https://huggingface.co/datasets/eduagarcia/enem_challenge) — "ENEM Challenge" de Silveira e Mauá, 2017–2018 | INEP | ENEM 2009 a 2017 | provas públicas do INEP |
| `originais/` | — | escritas para o Work Flower (estilo Cebraspe, FGV, FCC, Vunesp, Cesgranrio…) | do próprio Work Flower |

As provas em si são documentos públicos do INEP (ENEM) e da OAB. Nada foi copiado de sites de questões
(QConcursos, TEC Concursos e semelhantes proíbem a cópia). As provas públicas não trazem comentário; as
inéditas trazem gabarito comentado escrito junto com a questão.

## O que entrou e o que foi calculado (provas públicas)

- Ficaram de fora as questões anuladas, as sem gabarito e as do ENEM que dependem de imagem (gráfico,
  figura, tirinha, mapa, tabela), porque o cartão mostra só texto.
- **Matéria**: no ENEM, pela posição da questão no caderno — 2009: 1–45 Natureza, 46–90 Humanas, 91–135 Linguagens,
  136–180 Matemática; 2010–2016: 1–45 Humanas, 46–90 Natureza, 91–135 Linguagens, 136–180 Matemática;
  2017 em diante: 1–45 Linguagens, 46–90 Humanas, 91–135 Natureza, 136–180 Matemática.
  Na OAB, a área marcada no conjunto; nas provas sem marcação (exames XV a XXV), um classificador de palavras
  treinado nas provas marcadas, respeitando a ordem fixa das matérias na prova da FGV.
- **Assunto**: palavras-chave por matéria (`comum.php`); sem palavra-chave, fica "Geral".
- **Dificuldade**: *estimada* pelo tamanho do enunciado e das alternativas (terços dentro de cada matéria).
  Nas inéditas, a dificuldade foi definida por quem escreveu.

## Refazer os arquivos

- `questoes.jsonl.gz`: baixe numa pasta vazia
  `https://raw.githubusercontent.com/legal-nlp/oab-exams/master/official/raw/<exame>.txt` (2010-01 … 2018-25,
  inclusive 2012-06a e 2016-20a) e `https://huggingface.co/datasets/maritaca-ai/enem/resolve/main/<ano>.jsonl`
  (salvos como `maritaca2022.jsonl` etc.) e rode `php database/data/questoes/construir.php <pasta>`.
- `enem-2009-2017.jsonl.gz`: baixe as páginas
  `https://datasets-server.huggingface.co/rows?dataset=eduagarcia/enem_challenge&config=default&split=train&offset=<0,100,…,1400>&length=100`
  como `ec_<offset>.json` e rode `php database/data/questoes/construir-enem-2009-2017.php <pasta>`.
- Para recarregar a tabela: `php artisan db:seed --class=QuestoesBancoSeeder` (apaga e importa de novo).
