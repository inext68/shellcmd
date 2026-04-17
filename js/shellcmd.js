document.addEventListener('DOMContentLoaded', () => {

  const executeBtn = document.getElementById('shellcmd-execute');
  const outputBox = document.getElementById('shellcmd-output');
  const selectCmd = document.getElementById('shellcmd-command');

  if (!executeBtn || !outputBox || !selectCmd) {
    return;
  }

  executeBtn.addEventListener('click', async () => {
    outputBox.style.display = 'block';
    outputBox.textContent = '';

    const params = new URLSearchParams();
    params.append('command', selectCmd.value);
    params.append('itemtype', window.GLPI_ITEMTYPE);
    params.append('items_id', window.GLPI_ITEMS_ID);
    params.append('_glpi_csrf_token', window.GLPI_CSRF_TOKEN);

    const response = await fetch(
      CFG_GLPI.root_doc + '/plugins/shellcmd/ajax/stream.php',
      {
        method: 'POST',
        body: params,
      }
    );

    if (!response.ok || !response.body) {
      outputBox.textContent = 'ERROR: Unable to start streaming\n';
      return;
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();

    while (true) {
      const { value, done } = await reader.read();
      if (done) {
        break;
      }
      outputBox.textContent += decoder.decode(value, { stream: true });
      outputBox.scrollTop = outputBox.scrollHeight;
    }
  });
});