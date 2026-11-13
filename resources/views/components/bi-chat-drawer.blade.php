<!-- Floating BI AI Assistant Trigger Button -->
<div x-data="biChatApp()" x-init="init()" x-cloak>
    <button class="bi-chat-trigger" @click="isOpen = !isOpen" title="Ratio AI Business Intelligence Assistant">
        <span style="font-size:22px">🤖</span>
        <span class="bi-chat-label">AI Assistant</span>
        <span x-show="hasUnread" class="bi-chat-pulse"></span>
    </button>

    <!-- Slide-over Chat Drawer -->
    <div x-show="isOpen" class="bi-chat-overlay" @click.self="isOpen = false" style="display:flex">
        <div class="bi-chat-drawer">
            <!-- Header -->
            <div class="bi-chat-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="bi-avatar">🤖</div>
                    <div>
                        <div class="fw-bold" style="font-size:15px;color:var(--txt-primary)">Ratio AI Assistant</div>
                        <div style="font-size:11px;color:var(--clr-success)" class="d-flex align-items-center gap-1">
                            <span style="width:6px;height:6px;border-radius:50%;background:var(--clr-success)"></span>
                            Connected to Ratio ERP Engine
                        </div>
                    </div>
                </div>
                <button class="btn-icon" @click="isOpen = false" style="border:none;background:none;font-size:18px;cursor:pointer;color:var(--txt-secondary)">✕</button>
            </div>

            <!-- Quick Prompts -->
            <div class="bi-quick-prompts">
                <template x-for="p in quickPrompts" :key="p">
                    <button class="quick-prompt-btn" @click="sendPrompt(p)" x-text="p"></button>
                </template>
            </div>

            <!-- Messages Area -->
            <div class="bi-chat-body" x-ref="chatBody">
                <template x-for="(m, idx) in messages" :key="idx">
                    <div :class="['chat-bubble', m.sender]">
                        <div class="bubble-sender" x-text="m.sender === 'ai' ? '🤖 Ratio AI' : '👤 CEO'"></div>
                        <div class="bubble-text" x-html="formattedText(m.text)"></div>
                        <div class="bubble-time" x-text="m.time"></div>
                    </div>
                </template>
                <div x-show="loading" class="chat-bubble ai">
                    <div class="bubble-text">⏳ <em>Sedang menganalisis data database...</em></div>
                </div>
            </div>

            <!-- Input Area -->
            <form @submit.prevent="submitMessage()" class="bi-chat-footer">
                <input x-model="inputMsg" type="text" class="form-input bi-input" placeholder="Tanyakan omzet, stok, produk terlaris..." :disabled="loading" />
                <button type="submit" class="btn btn-primary" :disabled="loading || !inputMsg.trim()">Kirim</button>
            </form>
        </div>
    </div>
</div>

<style>
.bi-chat-trigger {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 1000;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 99px;
  background: linear-gradient(135deg, var(--clr-primary), var(--clr-primary-dark, #c2410c));
  color: #ffffff;
  border: none;
  font-weight: 700;
  font-size: 13.5px;
  box-shadow: 0 8px 24px rgba(234, 88, 12, 0.4);
  cursor: pointer;
  transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.bi-chat-trigger:hover { transform: translateY(-2px) scale(1.03); }

.bi-chat-pulse {
  width: 8px; height: 8px; border-radius: 50%; background: #22c55e;
  box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.3); animation: biPulse 1.5s infinite;
}
@keyframes biPulse {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
  70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
}

.bi-chat-overlay {
  position: fixed; inset: 0; z-index: 1001;
  background: rgba(15, 23, 42, 0.3); backdrop-filter: blur(4px);
  display: flex; justify-content: flex-end;
}
.bi-chat-drawer {
  width: 420px; max-width: 90vw; height: 100vh;
  background: var(--bg-surface); border-left: 1px solid var(--border);
  display: flex; flex-direction: column; box-shadow: var(--shadow-lg);
}
.bi-chat-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.bi-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--bg-muted); display: flex; align-items: center; justify-content: center; font-size: 18px; }
.bi-quick-prompts { padding: 10px 16px; display: flex; gap: 6px; flex-wrap: wrap; border-bottom: 1px solid var(--border); background: var(--bg-base); }
.quick-prompt-btn { font-size: 11.5px; font-weight: 600; padding: 4px 10px; border-radius: 99px; border: 1px solid var(--border); background: var(--bg-surface); color: var(--txt-secondary); cursor: pointer; }
.quick-prompt-btn:hover { border-color: var(--clr-primary); color: var(--clr-primary); }

.bi-chat-body { flex: 1; padding: 16px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; }
.chat-bubble { max-width: 85%; padding: 10px 14px; border-radius: 12px; font-size: 13px; line-height: 1.45; }
.chat-bubble.ai { align-self: flex-start; background: var(--bg-muted); color: var(--txt-primary); border-bottom-left-radius: 2px; }
.chat-bubble.user { align-self: flex-end; background: var(--clr-primary); color: #ffffff; border-bottom-right-radius: 2px; }
.bubble-sender { font-size: 10px; font-weight: 700; opacity: 0.7; margin-bottom: 2px; }
.bubble-time { font-size: 9.5px; opacity: 0.6; text-align: right; margin-top: 4px; }
.bi-chat-footer { padding: 12px 16px; border-top: 1px solid var(--border); display: flex; gap: 8px; }
.bi-input { flex: 1; font-size: 13px; }
</style>

<script>
function biChatApp() {
    return {
        isOpen: false,
        hasUnread: true,
        loading: false,
        inputMsg: '',
        quickPrompts: [
            'Ringkasan bisnis hari ini',
            'Berapa omzet hari ini?',
            'Total pengeluaran?',
            'Status stok bahan baku?',
            'Produk paling laku?',
            'Daftar supplier aktif?',
            'Profit bulan ini?'
        ],
        messages: [{
            sender: 'ai',
            text: 'Halo CEO! 👋 Saya **Ratio AI Assistant** — connected langsung ke seluruh database ERP Anda.\n\nAnda bisa bertanya tentang: **omzet**, **profit**, **pengeluaran**, **stok bahan baku**, **supplier**, **transaksi**, **karyawan**, atau **ringkasan bisnis** secara real-time.',
            time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        }],

        init() {},

        formattedText(t) {
            if (!t) return '';
            return t
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/\n/g, '<br>');
        },

        async sendPrompt(txt) {
            this.inputMsg = txt;
            await this.submitMessage();
        },

        async submitMessage() {
            const q = this.inputMsg.trim();
            if (!q || this.loading) return;

            const nowTime = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            this.messages.push({ sender: 'user', text: q, time: nowTime });
            this.inputMsg = '';
            this.loading = true;

            try {
                const r = await axios.post('/api/v1/dashboard/bi-chat', { message: q });
                this.messages.push({
                    sender: 'ai',
                    text: r.data?.reply || r.data?.message || 'Data diterima.',
                    time: r.data?.timestamp || nowTime
                });
            } catch(e) {
                this.messages.push({
                    sender: 'ai',
                    text: '⚠️ Maaf, terjadi kendala saat terhubung ke engine analytics.',
                    time: nowTime
                });
            } finally {
                this.loading = false;
                this.$nextTick(() => {
                    if (this.$refs.chatBody) {
                        this.$refs.chatBody.scrollTop = this.$refs.chatBody.scrollHeight;
                    }
                });
            }
        }
    }
}
</script>
