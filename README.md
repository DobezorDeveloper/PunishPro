# General

![Build Status](https://poggit.pmmp.io/shield.state/PunishPro)

PunishPro is a Minecraft PocketMine-MP plugin for managing player punishments. With this plugin, you can temporarily ban players, mute their chat, and unban or unmute them as needed.

## Features

- Temporarily ban players.
- Temporarily mute players' chat.
- Unban players.
- Unmute players' chat.
- Customizable messages.

## Commands

- `/tempban <player> <time> [reason]`: Temporarily ban a player.
- `/chatoff <player> <time> [reason]`: Temporarily mute a player's chat.
- `/unban <player>`: Unban a player.
- `/chaton <player>`: Unmute a player's chat.

## Permissions

- `punishpro.tempban`: Temporarily ban a player. (default: op)
- `punishpro.chatoff`: Temporarily mute a player's chat. (default: op)
- `punishpro.unban`: Unban a player. (default: op)
- `punishpro.chaton`: Unmute a player's chat. (default: op)

## Configuration

The plugin uses YAML files:

- `tempbans.yml`: Temporary ban data.
- `mutes.yml`: Temporary mute data.
- `messages.yml`: Customizable messages.
