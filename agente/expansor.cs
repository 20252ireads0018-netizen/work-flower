// Textos rápidos do Work Flower no Windows inteiro (compilado pelo agente.ps1 com Add-Type; não precisa instalar nada).
//
// - Um gancho de teclado (WH_KEYBOARD_LL) numa thread própria guarda só os últimos 40 caracteres digitados, em memória.
//   Nada é gravado em disco nem enviado ao site. Troca de janela, Enter, Tab, setas, Ctrl/Alt e cliques de atalho zeram.
// - Quando o fim do que foi digitado é um atalho (ex.: ";ass"), apaga o atalho com Backspace e digita o texto salvo
//   (SendInput com caracteres Unicode: acentos e emojis funcionam em qualquer programa).
// - Mesma regra do site (js/expansor.js): atalho que começa com letra ou número só vale no começo de uma palavra;
//   se dois atalhos servem, vale o mais longo. Variáveis: {data} {hora} {data_extenso} {dia_semana} {ano} {nome}
//   {primeiro_nome} {email} e {cursor} (onde o cursor fica).
// - Teclas injetadas por programas (inclusive as nossas) são ignoradas. Programas abertos como administrador não
//   recebem a troca (o Windows não deixa um programa comum digitar neles).
using System;
using System.Collections.Generic;
using System.Globalization;
using System.Runtime.InteropServices;
using System.Text;
using System.Text.RegularExpressions;
using System.Threading;

public static class WFExpansor
{
    // ---------------------------------------------------------------- Win32
    private delegate IntPtr HookProc(int nCode, IntPtr wParam, IntPtr lParam);

    [StructLayout(LayoutKind.Sequential)]
    private struct KBDLLHOOKSTRUCT { public uint vkCode; public uint scanCode; public uint flags; public uint time; public IntPtr extra; }

    [StructLayout(LayoutKind.Sequential)]
    private struct MOUSEINPUT { public int dx; public int dy; public uint mouseData; public uint dwFlags; public uint time; public IntPtr dwExtraInfo; }

    [StructLayout(LayoutKind.Sequential)]
    private struct KEYBDINPUT { public ushort wVk; public ushort wScan; public uint dwFlags; public uint time; public IntPtr dwExtraInfo; }

    [StructLayout(LayoutKind.Explicit)]
    private struct UNIAO { [FieldOffset(0)] public MOUSEINPUT mi; [FieldOffset(0)] public KEYBDINPUT ki; }

    [StructLayout(LayoutKind.Sequential)]
    private struct INPUT { public uint type; public UNIAO u; }

    [StructLayout(LayoutKind.Sequential)]
    private struct MSG { public IntPtr hwnd; public uint message; public IntPtr wParam; public IntPtr lParam; public uint time; public int x; public int y; }

    [DllImport("user32.dll", SetLastError = true)] private static extern IntPtr SetWindowsHookEx(int idHook, HookProc fn, IntPtr hMod, uint threadId);
    [DllImport("user32.dll")] private static extern bool UnhookWindowsHookEx(IntPtr hHook);
    [DllImport("user32.dll")] private static extern IntPtr CallNextHookEx(IntPtr hHook, int nCode, IntPtr wParam, IntPtr lParam);
    [DllImport("user32.dll")] private static extern int GetMessage(out MSG msg, IntPtr hWnd, uint min, uint max);
    [DllImport("user32.dll")] private static extern bool PostThreadMessage(uint threadId, uint msg, IntPtr wParam, IntPtr lParam);
    [DllImport("kernel32.dll")] private static extern uint GetCurrentThreadId();
    [DllImport("kernel32.dll", CharSet = CharSet.Unicode)] private static extern IntPtr GetModuleHandle(string name);
    [DllImport("user32.dll")] private static extern IntPtr GetForegroundWindow();
    [DllImport("user32.dll")] private static extern uint GetWindowThreadProcessId(IntPtr hWnd, IntPtr pid);
    [DllImport("user32.dll")] private static extern IntPtr GetKeyboardLayout(uint threadId);
    [DllImport("user32.dll")] private static extern short GetKeyState(int vk);
    [DllImport("user32.dll")] private static extern short GetAsyncKeyState(int vk);
    [DllImport("user32.dll", CharSet = CharSet.Unicode)]
    private static extern int ToUnicodeEx(uint vk, uint scan, byte[] state, StringBuilder buf, int len, uint flags, IntPtr layout);
    [DllImport("user32.dll", SetLastError = true)] private static extern uint SendInput(uint n, INPUT[] inputs, int size);

    private const int WH_KEYBOARD_LL = 13;
    private const uint LLKHF_INJECTED = 0x10, KEYEVENTF_KEYUP = 0x2, KEYEVENTF_UNICODE = 0x4;
    private const ushort VK_BACK = 0x08, VK_RETURN = 0x0D, VK_TAB = 0x09, VK_LEFT = 0x25;

    // ---------------------------------------------------------------- estado
    private static readonly HookProc proc = Callback;   // referência fixa: o coletor de lixo não pode levar o delegate
    private static IntPtr gancho = IntPtr.Zero;
    private static Thread thread;
    private static uint threadId;
    private static readonly StringBuilder buf = new StringBuilder();
    private static IntPtr ultimaJanela = IntPtr.Zero;
    private static volatile bool ligado = true;
    private static volatile int trocas = 0;
    private static readonly object trava = new object();
    private static List<KeyValuePair<string, string>> trechos = new List<KeyValuePair<string, string>>();
    private static string nome = "", email = "";
    private static readonly CultureInfo PT = new CultureInfo("pt-BR");

    public static bool Ligado { get { return ligado; } set { ligado = value; lock (trava) { buf.Length = 0; } } }
    public static bool Rodando { get { return gancho != IntPtr.Zero; } }
    public static int Quantidade { get { lock (trava) { return trechos.Count; } } }
    public static int Trocas { get { return trocas; } }

    /** Troca a lista de atalhos (arrays do mesmo tamanho) e os dados da pessoa para {nome} e {email}. */
    public static void Definir(string[] atalhos, string[] textos, string nomePessoa, string emailPessoa)
    {
        var l = new List<KeyValuePair<string, string>>();
        for (int i = 0; i < Math.Min(atalhos.Length, textos.Length); i++)
        {
            string a = atalhos[i] ?? "", t = textos[i] ?? "";
            if (a.Length == 0 || a.Length > 30 || t.Length == 0 || Regex.IsMatch(a, @"\s")) continue;
            l.Add(new KeyValuePair<string, string>(a, t.Length > 5000 ? t.Substring(0, 5000) : t));
        }
        lock (trava) { trechos = l; nome = nomePessoa ?? ""; email = emailPessoa ?? ""; buf.Length = 0; }
    }

    public static void Iniciar()
    {
        if (thread != null) return;
        var pronto = new ManualResetEvent(false);
        thread = new Thread(() =>
        {
            threadId = GetCurrentThreadId();
            caps = (GetKeyState(0x14) & 1) != 0;
            gancho = SetWindowsHookEx(WH_KEYBOARD_LL, proc, GetModuleHandle(null), 0);
            pronto.Set();
            MSG m;
            // o gancho só funciona com esta thread tratando mensagens (ela não faz mais nada, então nunca atrasa a digitação)
            while (GetMessage(out m, IntPtr.Zero, 0, 0) > 0) { }
            if (gancho != IntPtr.Zero) { UnhookWindowsHookEx(gancho); gancho = IntPtr.Zero; }
        });
        thread.IsBackground = true;
        thread.Name = "WorkFlowerExpansor";
        thread.Start();
        pronto.WaitOne(3000);
    }

    public static void Parar()
    {
        if (thread == null) return;
        PostThreadMessage(threadId, 0x0012 /* WM_QUIT */, IntPtr.Zero, IntPtr.Zero);
        thread.Join(2000);
        thread = null;
    }

    // ---------------------------------------------------------------- teclado
    private static IntPtr Callback(int nCode, IntPtr wParam, IntPtr lParam)
    {
        try
        {
            int msg = wParam.ToInt32();
            if (nCode >= 0 && ligado && (msg == 0x100 || msg == 0x104))
            {
                var k = (KBDLLHOOKSTRUCT)Marshal.PtrToStructure(lParam, typeof(KBDLLHOOKSTRUCT));
                if ((k.flags & LLKHF_INJECTED) == 0) Tecla(k);
            }
        }
        catch { /* o gancho nunca pode travar o teclado */ }
        return CallNextHookEx(gancho, nCode, wParam, lParam);
    }

    // no gancho de baixo nível o GetKeyState pode estar atrasado: modificadores pelo estado "agora" do teclado
    private static bool Apertada(int vk) { return (GetAsyncKeyState(vk) & 0x8000) != 0; }
    private static bool caps = false;

    private static void Tecla(KBDLLHOOKSTRUCT k)
    {
        uint vk = k.vkCode;
        if (vk == 0x14) { caps = !caps; return; }   // Caps Lock: acompanhado aqui mesmo
        // modificadores sozinhos não contam
        if (vk == 0x10 || vk == 0x11 || vk == 0x12 || (vk >= 0xA0 && vk <= 0xA5) || vk == 0x14 || vk == 0x5B || vk == 0x5C) return;

        IntPtr janela = GetForegroundWindow();
        string achado = null, texto = null;
        lock (trava)
        {
            if (janela != ultimaJanela) { buf.Length = 0; ultimaJanela = janela; }
            if (trechos.Count == 0) return;
            if (vk == VK_BACK) { if (buf.Length > 0) buf.Length--; return; }

            bool ctrl = Apertada(0x11), alt = Apertada(0x12);
            if (ctrl != alt) { buf.Length = 0; return; }   // Ctrl+algo ou Alt+algo: atalho de programa (Ctrl+Alt = AltGr, vale)

            var estado = new byte[256];
            if (Apertada(0x10)) estado[0x10] = 0x80;
            if (caps) estado[0x14] = 0x01;
            if (ctrl && alt) { estado[0x11] = 0x80; estado[0x12] = 0x80; }
            var sb = new StringBuilder(8);
            IntPtr layout = GetKeyboardLayout(GetWindowThreadProcessId(janela, IntPtr.Zero));
            // flag 4: não mexe no estado do teclado (senão os acentos ´ ~ ^ do ABNT2 deixariam de funcionar)
            int n = ToUnicodeEx(vk, k.scanCode, estado, sb, sb.Capacity, 4, layout);
            if (n < 0) return;                             // tecla de acento: espera a próxima
            if (n == 0) { buf.Length = 0; return; }        // setas, Home, F1...: o cursor pode ter mudado de lugar
            char c = sb[0];
            if (c < 0x20) { buf.Length = 0; return; }      // Enter, Tab, Esc
            buf.Append(sb.ToString(0, n));
            if (buf.Length > 40) buf.Remove(0, buf.Length - 40);

            achado = AcharSemTrava(buf.ToString(), out texto);
            if (achado == null) return;
            buf.Length = 0;
        }
        string at = achado, tx = texto;
        // fora do gancho: a última tecla do atalho ainda vai chegar ao programa; depois dela, apaga e digita
        ThreadPool.QueueUserWorkItem(_ => Expandir(at.Length, tx));
    }

    /** O atalho que termina exatamente no fim de "antes" (o mais longo), ou null. Público para testar a regra. */
    public static string Achar(string antes, out string texto)
    {
        lock (trava) { return AcharSemTrava(antes, out texto); }
    }

    private static string AcharSemTrava(string antes, out string texto)
    {
        string achado = null; texto = null;
        foreach (var t in trechos)
        {
            string a = t.Key;
            if (!antes.EndsWith(a, StringComparison.Ordinal) || (achado != null && achado.Length >= a.Length)) continue;
            if (char.IsLetterOrDigit(a[0]) && antes.Length > a.Length)
            {
                char p = antes[antes.Length - a.Length - 1];
                if (char.IsLetterOrDigit(p) || p == '_') continue;   // "ass" não troca dentro de "massa"
            }
            achado = a; texto = t.Value;
        }
        return achado;
    }

    // ---------------------------------------------------------------- troca
    public static string Montar(string modelo, out int depoisDoCursor)
    {
        DateTime d = DateTime.Now;
        string n, e;
        lock (trava) { n = nome; e = email; }
        string primeiro = n.Trim().Split(new[] { ' ', '\t' }, StringSplitOptions.RemoveEmptyEntries).Length > 0
            ? n.Trim().Split(new[] { ' ', '\t' }, StringSplitOptions.RemoveEmptyEntries)[0] : "";
        bool marcou = false;
        string t = Regex.Replace(modelo.Replace("\r\n", "\n").Replace("\r", "\n"), @"\{([a-zA-Z_]+)\}", m =>
        {
            switch (m.Groups[1].Value.ToLowerInvariant())
            {
                case "data": return d.ToString("dd/MM/yyyy", PT);
                case "hora": return d.ToString("HH:mm", PT);
                case "data_extenso": return d.Day + " de " + d.ToString("MMMM", PT) + " de " + d.Year;
                case "dia_semana": return d.ToString("dddd", PT);
                case "ano": return d.Year.ToString();
                case "nome": return n;
                case "primeiro_nome": return primeiro;
                case "email": return e;
                case "cursor": if (marcou) return ""; marcou = true; return "\u0000";
                default: return m.Value;
            }
        });
        int i = t.IndexOf('\u0000');
        t = t.Replace("\u0000", "");
        depoisDoCursor = i < 0 ? 0 : t.Length - i;
        return t;
    }

    private static void Tecla(List<INPUT> l, ushort vk)
    {
        l.Add(new INPUT { type = 1, u = new UNIAO { ki = new KEYBDINPUT { wVk = vk } } });
        l.Add(new INPUT { type = 1, u = new UNIAO { ki = new KEYBDINPUT { wVk = vk, dwFlags = KEYEVENTF_KEYUP } } });
    }

    private static void Expandir(int apagar, string modelo)
    {
        try
        {
            Thread.Sleep(45);
            int voltar;
            string texto = Montar(modelo, out voltar);
            var l = new List<INPUT>();
            for (int i = 0; i < apagar; i++) Tecla(l, VK_BACK);
            foreach (char c in texto)
            {
                if (c == '\n') { Tecla(l, VK_RETURN); continue; }
                if (c == '\t') { Tecla(l, VK_TAB); continue; }
                l.Add(new INPUT { type = 1, u = new UNIAO { ki = new KEYBDINPUT { wScan = c, dwFlags = KEYEVENTF_UNICODE } } });
                l.Add(new INPUT { type = 1, u = new UNIAO { ki = new KEYBDINPUT { wScan = c, dwFlags = KEYEVENTF_UNICODE | KEYEVENTF_KEYUP } } });
            }
            for (int i = 0; i < voltar; i++) Tecla(l, VK_LEFT);
            SendInput((uint)l.Count, l.ToArray(), Marshal.SizeOf(typeof(INPUT)));
            trocas++;
        }
        catch { }
    }
}
