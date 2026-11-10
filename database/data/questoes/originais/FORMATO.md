# Questões inéditas do Work Flower

Cada arquivo `*.json` desta pasta é uma área. Todas as questões aqui foram **escritas para o Work Flower**
(não são cópia de prova nem de site de questões) e trazem gabarito e comentário próprios.
São importadas junto com as provas públicas por `App\Support\QuestoesBanco::importar()`.

```json
{
  "area": "Tecnologia",
  "questoes": [
    {
      "ref": "WF-PROG-001",
      "materia": "Programação",
      "assunto": "Estruturas de repetição",
      "dificuldade": "facil",
      "estilo": "Cebraspe",
      "enunciado": "Texto da questão (pode ter \\n).",
      "alts": ["A", "B", "C", "D", "E"],
      "certa": 2,
      "comentario": "Por que a C está certa e por que as outras estão erradas."
    }
  ]
}
```

- `ref`: único em todo o banco (prefixo `WF-` + sigla da matéria + número de 3 dígitos).
- `dificuldade`: `facil`, `media` ou `dificil` (definida por quem escreveu, não estimada).
- `estilo`: banca cujo formato a questão imita (`Cebraspe`, `FGV`, `FCC`, `Vunesp`, `Cesgranrio`, `ENEM`…).
  A banca no filtro fica "Inéditas Work Flower"; o estilo vai no nome da prova.
- `alts`: 5 alternativas (A–E) ou, no estilo Cebraspe certo/errado, exatamente `["Certo", "Errado"]`.
- `certa`: índice da alternativa certa (0 = A).
- `comentario`: obrigatório, 1 a 4 frases, explica o gabarito.
