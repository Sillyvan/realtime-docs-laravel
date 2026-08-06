Little laravel project for leaning purposes, using inertia and reverb.

Basically a little word online thing

## Personal learnings

### Reverb + Echo

Reverb is the goto WebSocket server for laravel apps. But its a bit special because it uses the "Pusher" protocol which in simple terms is like Pub/Sub using JSON. Also has built in support for rooms.

Echo is the client wrapper around it.

### Events, Policies

Events can be multiple things but from what i have seen its usually classic dispatch and listener OR it can do broadcasting which was useful here.

Policies seem just like a auth layer on top of a model. so like can user access this model or what actions can he do.

### Yjs

Never used this before, but found it researching the topic, very useful for collaborative editing stuff.
Did all the updating, overwriting, and conflict logic for me.

## Familiar stuff

### Wayfinder

Basically RPC between laravel and typescript very cool nothing too complicated here.

### Inertia

I was already familiar with SSR based frameworks, this dosent feel too different you just end up passing data into a render function, which you get access to as a prop on the client.
